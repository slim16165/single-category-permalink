<?php
/**
 * Plugin Name: Single Category Permalink
 * Version:     2.6
 * Plugin URI:  https://coffee2code.com/wp-plugins/single-category-permalink/
 * Author:      Scott Reilly
 * Author URI:  https://coffee2code.com/
 * Text Domain: single-category-permalink
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Reduce permalinks (category or post) that include entire hierarchy of categories to just having the lowest level category.
 *
 * Compatible with WordPress 4.6 through 5.4+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/single-category-permalinks/
 *
 * @package Single_Category_Permalink
 * @author  Scott Reilly
 * @version 2.6
 */

/*
	Copyright (c) 2007-2020 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined('ABSPATH') or exit();


class c2c_SingleCategoryPermalink
{

    /**
     * Returns version of the plugin.
     *
     * @since 2.2
     */
    public static function version()
    {
        return '2.6';
    }

    /**
     * Initialization.
     *
     * @since 2.2
     */
    public static function init()
    {
        // Load textdomain.
        load_plugin_textdomain('single-category-permalink');

        add_filter('term_link',             array(__CLASS__, 'category_link'),              1, 3);
        add_filter('post_link',             array(__CLASS__, 'post_link'),                  1, 2);
        add_filter('template_redirect',     array(__CLASS__, 'template_redirect'));
        add_filter('wpseo_canonical',       array(__CLASS__, 'yoast_change_seo_canonical'), 1, 1 );
        add_filter('wpseo_opengraph_url',   array(__CLASS__, 'yoast_change_seo_opengraph_url'), 10);
        add_filter('wpseo_schema_article',  array(__CLASS__, 'example_change_article') );
        add_filter('yoast_seo_development_mode', '__return_true' );
        //remove_action('wp_head',
        //add_action( 'wp_head', [ $this, 'call_wpseo_head' ], 1 );
    }

    /**
     * Changes
     * @param array $data Schema.org Article data array.
     * @return void Schema.org Article data array.
     */
    public static function example_change_article( $data )
    {
        //self::PrintStackTrace();

        //YoastSEO()->context_memoizer;

        //PC::debug(YoastSEO()->meta->for_current_page());
        //PC::debug($data);

        //Change wp-content\plugins\wordpress-seo-premium\src\presenters\canonical-presenter.php\
        //Add $this->presentation->canonical = get_permalink();

        YoastSEO()->meta->for_current_page()->canonical = get_permalink();
        //YoastSEO()->meta->context_memoizer->for_current_page()->canonical = get_permalink();
    }

    public static function yoast_change_seo_canonical( $canonical )
    {
        if ( is_single() )
        {
            return get_permalink();
        }
        else
            return $canonical;
    }

    public static function yoast_change_seo_opengraph_url( $canonical )
    {
        return get_permalink();
    }

    /**
     * Returns post URI for a given post.
     *
     * If the post permalink structure includes %category%, then this function
     * kicks into gear to reduce a hierarchical category structure to its lowest
     * category.
     *
     * @param string $permalink The default URI for the post
     * @param WP_Post $post The post
     * @return string             The post URI.
     */
    public static function post_link($permalink, $post)
    {
//        $redis = new Redis();
//        $redis->connect('127.0.0.1', 12345);
//        $redis->auth('MyPassword');

//        echo "Server is running: ".$redis->ping();

        $cachedRedirect = wp_cache_get("$permalink");
        if($cachedRedirect)
        {
//            PC::debug($cachedRedirect);
            return $cachedRedirect;
        }


        $permalink_structure = get_option('permalink_structure');

        // Only do anything if '%category%' is part of the post permalink
        if (strpos($permalink_structure, '%category%') !== false)
        {
            $categoryOtherMethod = self::GetLastCategory($post);
            $category = self::get_post_primary_category($post);

//            PC::debug($categoryOtherMethod);
//            PC::debug($category);

            // Find category hierachy for the category. By default, these would be part of the full category permalink.
            $category_hierarchy = $category->slug;
 //           PC::debug($category->parent);

            if ($parent = $category->parent /* the id of the parent category or 0*/)
            {
                $category_hierarchy = get_category_parents($parent, false, '/', true) . $category->slug;
                //medicina-e-salute/integratori
                //PC::debug("entrato");
            }

            // Now that the permalink component involving category hierarchy consists of is known,
            // replace it with the main category slug
            //PC::debug($category_hierarchy);
            //PC::debug($category->slug);

            $oldPermalink = $permalink;
            $permalink = str_replace($category_hierarchy, $category->slug, $permalink);
            wp_cache_set($oldPermalink, $permalink);
            //PC::debug($oldPermalink);
            //debug($permalink);
        }

        //PC::debug($permalink);
        return $permalink;
    }

    protected static function PrintStackTrace(): void
    {
        $e = new \Exception;
        var_dump($e->getTraceAsString());
    }

    /**
     * @param WP_Post $post
     * @return array|mixed|object|WP_Error|null
     */
    private static function GetLastCategory(WP_Post $post)
    {
        // See the problems when a post is under more categories
        // https://stackoverflow.com/questions/45693271/getting-wordpress-single-post-primary-category

        // Find the canonical category for the post (assigned category with
        // lowest id)
        $cats = get_the_category($post->ID);
        if ($cats)
        {
            $cats = wp_list_sort($cats, 'term_id', 'asc');
            $category = $cats[0];
        }
        else
        {
            $category = get_category(absint(get_option('default_category')));
        }
        return $category;
    }

    /**
     * There is not primary category in wordpress if you have installed Yoast SEO plugin then a new feature will be appear on Single Posts category selection in admin area in order to choose primary category .
     * @param WP_Post $post
     * @param string $term
     * @return array|mixed|object|WP_Error|null
     */
    private static function get_post_primary_category(WP_Post $post, $term = 'category')
    {
        if (class_exists('WPSEO_Primary_Term'))
        {
            // Show Primary category by Yoast if it is enabled & set
            $wpseo_primary_term = new WPSEO_Primary_Term($term, $post->ID);
            $primary_term = get_term($wpseo_primary_term->get_primary_term());

            if (!is_wp_error($primary_term))
            {
                //PC::debug($primary_term);
                return $primary_term;
            }
        }

        if (empty($return['primary_category']))
        {
            //PC::debug("primary_category è vuota");
            return self::GetLastCategory($post);
        }
    }

    /**
     * Redirects fully hierarchical category links to the single category link.
     *
     * @since 2.0
     */
    public static function template_redirect()
    {
        global $wp_query, $post;

        $redirect = null;
        $category_name = isset($wp_query->query['category_name']) ? $wp_query->query['category_name'] : '';

        if (is_category())
        {
            if ($category_name && $category_name != $wp_query->query_vars['category_name'])
            {
                $redirect = self::category_link('', $wp_query->query_vars['cat']);
            }
        }
        elseif (is_single())
        {
            //PC::debug($category_name);

            if ($category_name && substr_count($category_name, '/') >= 1)
            {
                $redirect = get_permalink($post);
            }
        }

        if ($redirect)
        {
            wp_redirect($redirect, self::get_http_redirect_status());
        }
    }

    /**
     * Returns category URI for a given category.
     *
     * If the given category is hierarchical, then this function kicks into gear to
     * reduce a hierarchical category structure to its lowest category in the link.
     *
     * @param string $catlink The default URI for the category
     * @param int $category_id The category ID
     * @param string $taxonomy Taxonomy slug.
     * @return string The category URI
     */
    public static function category_link($catlink, $category_id, $taxonomy)
    {
        global $wp_rewrite;

        // Bail early if taxonomy is not 'category'.
        if ('category' !== $taxonomy)
        {
            return $catlink;
        }

        $catlink = $wp_rewrite->get_category_permastruct();

        if (!$catlink)
        {
            $file = trailingslashit(get_option('siteurl'));
            $catlink = $file . '?cat=' . $category_id;
        }
        else
        {
            $category = get_category($category_id);
            if (is_wp_error($category))
            {
                return $category;
            }
            $category_nicename = $category->slug;

            //$catlink = str_replace('/category/', '/', $catlink);
            $catlink = str_replace('%category%', $category_nicename, $catlink);
            $catlink = home_url(user_trailingslashit($catlink, 'category'));
        }

        return $catlink;
    }

    /**
     * Returns the HTTP status to use for redirects.
     *
     * @return string
     * @uses filter c2c_single_category_redirect_code
     *
     * @since 2.2
     */
    public static function get_http_redirect_status()
    {
        /**
         * Filters the HTTP status code used for redirects.
         *
         * @param int The HTTP status code to be used for redirects. Default 301.
         * @since 2.0
         *
         */
        return (int)apply_filters('c2c_single_category_redirect_status', 301);
    }

} // end c2c_SingleCategoryPermalink

add_action('plugins_loaded', array('c2c_SingleCategoryPermalink', 'init'), 1);