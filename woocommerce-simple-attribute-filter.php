<?php

/*
 * Plugin Name:       Woocommerce Simple Attribute Filter
 * Description:       Simple Dropdown filter for woocommerce
 * Version:           0.1
 * Author:            Habibul Islam
 * Author URI:        http://github.com/hihabib
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-simple-attribute-filter-by-hasib
 */

namespace WooFilter\Filter;

use WP_Widget;

if (class_exists('WooCommerce')) {
    // Creating the widget
    class Filter extends WP_Widget
    {
        function __construct()
        {
            parent::__construct('redparts_series_filter', "WOFH: Filter With Series", ['description' => "Filter With series"]);
        }

        // attribute filter
        public static function get_all_woocommerce_attributes()
        {
            global $wpdb;

            // Define the taxonomy prefix for product attributes
            $attribute_taxonomy_prefix = 'pa_%';

            // Prepare the query to get all attribute taxonomies
            $query = $wpdb->prepare(
                "SELECT DISTINCT tt.taxonomy
         FROM {$wpdb->term_taxonomy} AS tt
         WHERE tt.taxonomy LIKE %s",
                $attribute_taxonomy_prefix
            );

            // Execute the query to get attribute taxonomies
            $attribute_taxonomies = $wpdb->get_results($query);

            // Initialize an array to hold the results
            $attributes_array = array();

            // Loop through each attribute taxonomy to get its terms
            foreach ($attribute_taxonomies as $attribute_taxonomy) {
                $taxonomy = $attribute_taxonomy->taxonomy;
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ));

                // Add each attribute term to the results array
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $attributes_array[] = array(
                            'name' => wc_attribute_label($taxonomy),
                            'slug' => str_replace('pa_', '', $taxonomy)
                        );
                        break;  // Only need to add the taxonomy once
                    }
                }
            }

            return $attributes_array;
        }


        /**
         * Retrieves the values and slugs of a given WooCommerce product attribute.
         *
         * This function queries the WordPress database to get all the terms and slugs associated with a specific
         * WooCommerce product attribute. The attribute name is provided as a parameter, and the function
         * returns an associative array with attribute values (term names) as keys and slugs as values.
         *
         * @param string $attribute_slug The slug of the product attribute (e.g., 'color', 'size').
         * @return array An associative array of attribute values (term names) as keys and slugs as values, if found, otherwise an empty array.
         */
        function get_attribute_value(string $attribute_slug)
        {
            global $wpdb;

            // Prepare the taxonomy name
            $taxonomy = 'pa_' . sanitize_title($attribute_slug);

            // Get the terms and slugs of the attribute
            $attribute_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.name, t.slug
         FROM {$wpdb->prefix}terms AS t
         INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
         WHERE tt.taxonomy = %s",
                    $taxonomy
                ),
                ARRAY_A
            );

            // Construct the associative array with term names as keys and slugs as values
            $attribute_values = [];
            foreach ($attribute_data as $data) {
                $attribute_values[$data['slug']] = $data['name'];
            }

            return $attribute_values;
        }

        // Creating widget front-end
        public function widget($args, $instance)
        {
            $title = apply_filters('widget_title', $instance['title']);

            // before and after widget arguments are defined by themes
            echo $args['before_widget'];
            if (!empty($title)) {
                echo $args['before_title'] . $title . $args['after_title'];
            }

            // This is where you run the code and display the output
            if ($instance['attribute'] !== ''):

                ?>
                <form id="woo-filter-<?php echo $instance['attribute']; ?>"
                      action="<?php echo esc_url(wc_get_page_permalink('shop')) . "?" . http_build_query($_GET); ?>">
                    <select name="filter-<?php echo $instance['attribute']; ?>"
                            id="filter-<?php echo $instance['attribute']; ?>">
                        <?php
                        $i = 0;
                        foreach (self::get_attribute_value($instance['attribute']) as $slug => $name) : ?>
                            <?php $search_param = 'filter_' . $instance['attribute']; ?>
                            <?php if($i === 0) : ?>
                                <option <?php echo isset($_GET[$search_param]) && (string)$_GET[$search_param] === (string)$slug ? 'selected' : '' ?>
                        value="NULL"> Select <?php echo $instance['attribute']; ?>  </option>
                           <?php  endif; ?>
                            <option <?php echo isset($_GET[$search_param]) && (string)$_GET[$search_param] === (string)$slug ? 'selected' : '' ?>
                                    value="<?php echo $slug ?>"> <?php echo $name; ?> </option>

                        <?php $i++; endforeach; ?>
                    </select>
                </form>
                <script>
                    {
                        const form = document.querySelector(`#woo-filter-<?php echo $instance['attribute']; ?>`);
                    const select = document.querySelector(`#filter-<?php echo $instance['attribute']; ?>`);
                  select.addEventListener('change', function(){
                        const url = new URL(window.location.href);
                        const searchParams = url.searchParams;
                        const selectedValue = document.querySelector(`#filter-<?php echo $instance['attribute']; ?>`).value;
                        if(selectedValue !== "NULL"){
                            searchParams.set('filter_<?php echo $instance['attribute']; ?>', selectedValue);
                        } else {
                            searchParams.delete('filter_<?php echo $instance['attribute']; ?>');
                        }
                        //console.log(url.toString());
                        window.location.href = url;
                    });

                }
                </script>
            <?php

            endif;
            echo $args['after_widget'];
        }

        // Widget Settings Form
        public function form($instance)
        {
            $title = isset($instance['title']) ? $instance['title'] : "Filter";
            $attributeValue = isset($instance['attribute']) ? $instance['attribute'] : "";

            // Widget admin form
            ?>
            <p>
                <label for="<?php echo $this->get_field_id('title'); ?>">Title</label>
                <input
                        class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                        name="<?php echo $this->get_field_name('title'); ?>"
                        type="text"
                        value="<?php echo esc_attr($title); ?>"
                />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('attribute'); ?>">Attribute</label>
                <select class="widefat" name="<?php echo $this->get_field_name('attribute') ?>"
                        id="<?php echo $this->get_field_id('attribute'); ?>">
                    <?php foreach (self::get_all_woocommerce_attributes() as $attribute) : ?>
                        <option <?php echo $attributeValue === $attribute['slug'] ? 'selected' : ''; ?>
                                value="<?php echo $attribute['slug']; ?>"><?php echo $attribute['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </p>

            <?php
        }

        // Updating widget replacing old instances with new
        public function update($new_instance, $old_instance)
        {
            $instance = array();
            $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
            $instance['attribute'] = (!empty($new_instance['attribute'])) ? strip_tags($new_instance['attribute']) : '';

            return $instance;
        }

        // Class wpb_widget ends here
    }


    add_action('widgets_init', function () {
        register_widget(new Filter());
    });

}

