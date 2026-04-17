<?php
/*
 * Plugin Name: Showtimes
 * Plugin URI: https://github.com/OllieJones/showtimes
 * Description: Display shows. Shows are posts with the category "show"
 * Version: 0.5.0
 * Author: Oliver Jones
 * Author URI: https://github.com/OllieJones/
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Tested up to: 7.0
 * Text Domain: showtimes
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

namespace showtimes {
    define( 'showtimes_KEY', 'Showtime' );
    define( 'showtimes_NAME', 'showtime' );
    define( 'showtimes_SLUG', 'show' );

    add_action( 'init', function () {
        add_filter( 'the_title', function ( $title, $post_id ) {
            if ( ! $post_id ) {
                return $title;
            }
            $found = get_category( $post_id, showtimes_SLUG );
            if ( $found ) {

                list( $showtime, $iso_showtime ) = get_showtime( $post_id, showtimes_KEY );

                if ( $showtime ) {
                    $title = $showtime . ' ' . $title;
                }

            }

            return $title;
        }, 10, 2 );

        if ( is_admin() ) {

            $default = date( 'Y-m-d', strtotime( 'tomorrow' ) ) . 'T18:00:00';

            register_post_meta( 'post', showtimes_KEY, array(
                    'object_type'  => 'post',
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'label'        => __( 'Showtime', 'showtimes' ),
                    'description'  => __( 'The time and date of the event.', 'showtimes' ),
            ) );

            add_filter( 'manage_post_posts_columns', function ( $columns ) {
                $columns[ showtimes_NAME ] = __( 'Showtime', 'showtimes' );

                return $columns;
            } );


            add_action( 'manage_post_posts_custom_column', function ( $column, $post_id ) {
                if ( 'post' === get_post_type( $post_id ) && get_category( $post_id, showtimes_SLUG ) ) {
                    if ( $column === showtimes_NAME ) {
                        list( $showtime, $isotime ) = get_showtime( $post_id, showtimes_KEY );
                        $showtime = $showtime ?: '';
                        echo esc_html( $showtime ) . '<span class="iso" data-iso="' . esc_attr( $isotime ) . '"></span>';
                    }
                }
            }, 10, 2 );

            add_action( 'quick_edit_custom_box', function ( $column, $post_type ) {
                if ( 'post' !== $post_type || showtimes_NAME !== $column ) {
                    return;
                }
                ?>
                <fieldset class="inline-edit-col-right <?php echo esc_attr( showtimes_NAME ) ?>">
                    <div class="inline-edit-col">
                        <label>
                            <span class="title"><?php _e( 'Showtime', 'showtimes' ); ?></span>
                            <span class="input-text-wrap">
							<input
                                    type="datetime-local"
                                    id="<?php echo esc_attr( showtimes_NAME ) ?>"
                                    name="<?php echo esc_attr( showtimes_NAME ) ?>"
                                    value=""/>
						</span>
                        </label>
                    </div>
                </fieldset>
                <?php
            }, 10, 2 );

            add_action( 'save_post_post', function ( $post_id, $post, $update ) {
                $term = get_category( $post_id, showtimes_SLUG );
                if ( ! $term ) {
                    return;
                }
                if ( isset( $_POST[ showtimes_NAME ] ) ) {
                    update_post_meta( $post_id, showtimes_KEY, sanitize_text_field( $_POST[ showtimes_NAME ] ) );
                }
            }, 10, 3 );
        }

        add_action( 'admin_footer-edit.php', function () {
            global $typenow;
            if ( 'post' !== $typenow ) {
                return;
            }
            ?>
            <script>
                jQuery(function ($) {
                    if ('undefined' === typeof inlineEditPost) return

                    const wp_inline_edit = inlineEditPost.edit
                    inlineEditPost.edit = function (id) {
                        wp_inline_edit.apply(this, arguments)
                        const postId = ('object' === typeof (id)) ? parseInt(this.getId(id)) : 0

                        if (postId > 0) {
                            const editRow = $('#edit-' + postId)
                            const showtime = $('#post-' + postId).find('td.<?php echo esc_attr( showtimes_NAME )?> span.iso').data('iso')
                            if ('string' === typeof showtime) {
                                editRow.find('input[name="<?php echo esc_attr( showtimes_NAME )?>"]').val(showtime.trim())
                            } else {
                                editRow.find('fieldset.<?php echo esc_attr( showtimes_NAME )?>').hide()
                            }
                        }
                    };
                });
            </script>
            <?php
        } );

    } );

    /** Get the WP_Term for a category slug if it exists.
     *
     * @param int $post_id The post id to look up.
     * @param string $slug The metadata's slug to find.
     *
     * @return false|\WP_Term
     */
    function get_category( $post_id, $slug ) {
        $categories = get_the_category( $post_id );

        foreach ( $categories as $category ) {
            if ( $slug === $category->slug ) {
                return $category;
            }
        }

        return false;
    }

    /**
     * @param int $post_id The post id to look up.
     * @param string $meta_key The metadata to use. Default 'Showtime'
     *
     * @return array|false[] (Date-formatted string, iso string)
     */
    function get_showtime( $post_id, $meta_key ) {
        $showtime     = false;
        $showtime_iso = get_post_meta( $post_id, $meta_key, true );

        if ( $showtime_iso ) {
            /* We have a stored time. */
            try {
                $tzone    = get_option( 'timezone_string' );
                $tzone    = $tzone ?: 'UTC';
                $showtime = new \DateTimeImmutable( $showtime_iso, new \DateTimeZone( $tzone ) );
            } catch ( \Exception $e ) {
                $showtime = false;
                $showtime_iso = false;
            }
            if ( $showtime ) {
                $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
                $showtime    = wp_date( $date_format, (int) $showtime->getTimestamp() );
                $showtime    = str_replace( ':00', '', $showtime );
            }
        }
        if ( ! $showtime_iso ) {
            /* Default ISO time. */
            $showtime_iso = $showtime_iso ?: date( 'Y-m-d', strtotime( 'tomorrow' ) ) . 'T18:00:00';
        }

        return array( $showtime, $showtime_iso );

    }
}