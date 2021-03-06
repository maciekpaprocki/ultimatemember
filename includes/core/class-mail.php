<?php
namespace um\core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Mail' ) ) {
    class Mail {

        var $email_templates = array();

        function __construct() {

            $this->path_by_slug = apply_filters( 'um_email_templates_path_by_slug', array() );

            //mandrill compatibility
            add_filter( 'mandrill_nl2br', array( &$this, 'mandrill_nl2br' ) );

        }


        /**
         * Mandrill compatibility
         *
         * @param $nl2br
         * @param string $message
         * @return bool
         */
        function mandrill_nl2br( $nl2br, $message = '' ) {

            // text emails
            if ( ! um_get_option( 'email_html' ) ) {
                $nl2br = true;
            }

            return $nl2br;

        }


        /**
         * Send Email function
         *
         * @param string $email
         * @param null $template
         * @param array $args
         */
        function send( $email, $template, $args = array() ) {

            if ( ! is_email( $email ) ) return;
            if ( um_get_option( $template . '_on' ) != 1 ) return;

            $this->attachments = null;
            $this->headers = 'From: '. um_get_option('mail_from') .' <'. um_get_option('mail_from_addr') .'>' . "\r\n";

            $this->subject = um_convert_tags( um_get_option( $template . '_sub' ), $args );

            $this->message = $this->prepare_template( $template, $args );

            add_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' ) );
            // Send mail
            wp_mail( $email, $this->subject, $this->message, $this->headers, $this->attachments );
            remove_filter( 'wp_mail_content_type', array( &$this, 'set_content_type' )  );
        }


        /**
         * @param $slug
         * @param $args
         * @return bool|string
         */
        function get_email_template( $slug, $args = array() ) {

            $located = $this->locate_template( $slug );

            $located = apply_filters( 'um_email_template_path', $located, $slug, $args );

            if ( ! file_exists( $located ) ) {
                _doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $located ), '2.1' );
                return false;
            }

            ob_start();

            do_action( 'um_before_email_template_part', $slug, $located, $args );

            include( $located );

            do_action( 'um_after_email_template_part', $slug, $located, $args );

            return ob_get_clean();
        }


        /**
         * Prepare email template to send
         *
         * @param $slug
         * @param $args
         * @return mixed|string
         */
        function prepare_template( $slug, $args = array() ) {
            ob_start();

            if ( um_get_option( 'email_html' ) ) { ?>

                <html>
                    <?php do_action( 'um_before_email_template_body', $slug, $args ); ?>

                    <body <?php echo apply_filters( 'um_email_template_body_attrs', 'style="background: #f2f2f2;-webkit-font-smoothing: antialiased;-moz-osx-font-smoothing: grayscale;"', $slug ) ?>>

                        <?php echo $this->get_email_template( $slug, $args ); ?>

                    </body>
                </html>

            <?php } else {

                echo $this->get_email_template( $slug, $args );

            }

            $message = ob_get_clean();

            // Convert tags in email template
            return um_convert_tags( $message, $args );
        }


        /**
         * Locate a template and return the path for inclusion.
         *
         * @access public
         * @param string $template_name
         * @return string
         */
        function locate_template( $template_name ) {
            //WPML compatibility and multilingual email templates
            $lang = get_locale();
            $arr_english_lang = array( 'en', 'en_US', 'en_NZ', 'en_ZA', 'en_AU', 'en_GB' );

            if ( in_array( $lang, $arr_english_lang ) || strpos( $lang , 'en_' ) > -1 || empty( $lang ) || $lang == 0 ) {
                $lang = '';
            } else {
                $lang .= '/';
            }

            // check if there is template at theme folder
            $template = locate_template( array(
                trailingslashit( 'ultimate-member/email' ) . $lang . $template_name . '.php'
            ) );

            //if there isn't template at theme folder get template file from plugin dir
            if ( ! $template ) {
                $path = ! empty( $this->path_by_slug[ $template_name ] ) ? $this->path_by_slug[ $template_name ] : um_path . 'templates/email';
                $template = trailingslashit( $path ) . $lang . $template_name . '.php';
            }

            // Return what we found.
            return apply_filters( 'um_locate_email_template', $template, $template_name );
        }


        /**
         * Locate a template and return the path for inclusion.
         *
         * @access public
         * @param string $template_name
         * @param bool $html
         * @return string
         */
        function template_in_theme( $template_name, $html = false ) {
            //WPML compatibility and multilingual email templates
            $lang = get_locale();
            $arr_english_lang = array( 'en', 'en_US', 'en_NZ', 'en_ZA', 'en_AU', 'en_GB' );

            if ( in_array( $lang, $arr_english_lang ) || strpos( $lang , 'en_' ) > -1 || empty( $lang ) || $lang == 0 ) {
                $lang = '';
            } else {
                $lang .= '/';
            }

            $ext = ! $html ? '.php' : '.html';

            // check if there is template at theme folder
            $template = locate_template( array(
                trailingslashit( 'ultimate-member/email' ) . $lang . $template_name . $ext
            ) );

            // Return what we found.
            return ! $template ? false : true;
        }


        /**
         * Method returns expected path for template
         *
         * @access public
         * @param string $location
         * @param string $template_name
         * @param bool $html
         * @return string
         */
        function get_template_file( $location, $template_name, $html = false ) {
            $template_path = '';

            $ext = ! $html ? '.php' : '.html';

            switch( $location ) {
                case 'theme':
                    $template_path = trailingslashit( get_stylesheet_directory() . '/ultimate-member/email' ) . $template_name . $ext;
                    break;
                case 'plugin':
                    $path = ! empty( $this->path_by_slug[ $template_name ] ) ? $this->path_by_slug[ $template_name ] : um_path . 'templates/email';
                    $template_path = trailingslashit( $path ) . $template_name . $ext;
                    break;
            }

            //return apply_filters( 'wpc_client_template_location', $template_path, $location, $template_name, $path );
            return $template_path;
        }


        /**
         * Set email content type
         *
         *
         * @param $content_type
         * @return string
         */
        function set_content_type( $content_type ) {

            if ( um_get_option( 'email_html' ) ) {
                return 'text/html';
            } else {
                return 'text/plain';
            }

        }


        /**
         * Ajax copy template to the theme
         *
         * @param bool $template
         * @return bool
         */
        function copy_email_template( $template = false ) {

            $in_theme = $this->template_in_theme( $template );
            if ( $in_theme ) {
                return false;
            }

            $plugin_template_path = $this->get_template_file( 'plugin', $template );
            $theme_template_path = $this->get_template_file( 'theme', $template );

            $temp_path = str_replace( trailingslashit( get_stylesheet_directory() ), '', $theme_template_path );
            $temp_path = str_replace( '/', DIRECTORY_SEPARATOR, $temp_path );
            $folders = explode( DIRECTORY_SEPARATOR, $temp_path );
            $folders = array_splice( $folders, 0, count( $folders ) - 1 );
            $cur_folder = '';
            $theme_dir = trailingslashit( get_stylesheet_directory() );

            foreach ( $folders as $folder ) {
                $prev_dir = $cur_folder;
                $cur_folder .= $folder . DIRECTORY_SEPARATOR;
                if ( ! is_dir( $theme_dir . $cur_folder ) && wp_is_writable( $theme_dir . $prev_dir ) ) {
                    mkdir( $theme_dir . $cur_folder, 0777 );
                }
            }

            if ( copy( $plugin_template_path, $theme_template_path ) ) {
                return true;
            } else {
                return false;
            }
        }


        function delete_email_template() {
            $template = $_POST['email_key'];

            $in_theme = $this->template_in_theme( $template );
            if ( ! $in_theme ) {
                wp_send_json_error( new \WP_Error( 'template_in_theme', __( 'Template does not exists in theme', 'ultimate-member' ) ) );
            }

            $theme_template_path = $this->get_template_file( 'theme', $template );

            if ( unlink( $theme_template_path ) ) {
                wp_send_json_success();
            } else {
                wp_send_json_error( new \WP_Error( 'template_not_exists', __( 'Can not remove template from theme', 'ultimate-member' ) ) );
            }
        }

    }
}