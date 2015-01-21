<?php
/**
 * Plugin Name: Bulk Attachment Transfer (BAT)
 * Plugin URI: http://github.com/kander/wordpress-bulk-attachment-transfer
 * Description: Easily transfer a lot of files from one Wordpress install to another.
 * Includes replacing the links to your files in posts and metadata.
 *
 * Version: 1.0
 *
 * Author: Sander Bol / Clansman BV
 * Author URI: http://www.clansman.nl
 *
 * Based on AttachmentImporter by Toasted Lime (http://www.toastedlime.com )
 *
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bulk-att-xfer
 */
class BulkAttachmentTransfer
{

    protected $wpdb;

    public function __construct( wpdb $wpdb )
    {
        $this->wpdb = $wpdb;
    }

    static public function registerHooks( BulkAttachmentTransfer $object )
    {
        add_action( 'admin_enqueue_scripts', array( $object, 'registerJavascript' ) );
        add_action( 'admin_menu', array( $object, 'registerPage' ) );
        add_action( 'wp_ajax_bat_upload', array( $object, 'processUpload' ) );
        $plugin = plugin_basename( __FILE__ );
        add_filter( "plugin_action_links_" . $plugin, array( $object, 'addPluginLink') );
    }

    public function registerJavascript()
    {
        wp_register_script(
            'bat-main',
            plugins_url( 'js/bat-main.js', __FILE__ ),
            array( 'jquery', 'jquery-ui-tooltip', 'jquery-ui-progressbar', 'threadpool' ),
            20150119,
            true
        );

        wp_register_script(
            'threadpool',
            plugins_url( 'lib/threadpool.min.js', __FILE__ ),
            array(),
            20150120,
            true
        );
    }

    public function registerPage()
    {
        register_importer(
            'bulk-att-xfer',
            'Bulk Attachment Transfer',
            'Transfer attachments from an existing Wordpress installation using the WordPress export file.',
            array( $this, 'optionsPage' )
        );
    }

    public function addPluginLink($links) {
        $settings_link = '<a href="admin.php?import=bulk-att-xfer">' . __( 'Run Import' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    public function optionsPage()
    {
        wp_localize_script( 'bat-main', 'BulkAttXfer', array(
            'emptyInput'    => __( 'Please select a Wordpress Export (WXR) file.', 'bulk-att-xfer' ),
            'noAttachments' => __( 'We parsed the Wordpress Export file, but didn\'t find any attachments.', 'bulk-att-xfer'),
            'parsing'       => __( 'Parsing the file.', 'bulk-att-xfer' ),
            'importing'     => __( 'Importing file ', 'bulk-att-xfer' ),
            'done'          => __( 'All done!', 'bulk-att-xfer' ),
            'fatalUpload'   => __( 'There was a fatal error. Check the last entry in the error log below.', 'bulk-att-xfer' )
        ) );

        wp_localize_script( 'bat-main', 'BulkAttXferConfig', array(
            'nonce' => wp_create_nonce( 'import-attachment-plugin' ),
            'urls' => array(
                'siteurl' => get_option('siteurl'),
                'worker' => plugins_url( 'js/worker.js', __FILE__ ),
            )
        ) );

        wp_enqueue_script( 'bat-main' );
        wp_enqueue_style( 'jquery-ui', plugins_url( 'inc/jquery-ui.css', __FILE__ ) );
        wp_enqueue_style( 'bulk-att-xfer', plugins_url( 'inc/style.css', __FILE__ ) );

        include( __DIR__ . '/templates/page.php' );
    }

    private function extractParametersFromRequest( $post )
    {
        $parameters = array(
            'url'               => $_POST['url'],
            'post_title'        => $_POST['title'],
            'link'              => $_POST['link'],
            'pubDate'           => $_POST['pubDate'],
            'post_author'       => $_POST['creator'],
            'guid'              => $_POST['guid'],
            'import_id'         => $_POST['post_id'],
            'post_date'         => $_POST['post_date'],
            'post_date_gmt'     => $_POST['post_date_gmt'],
            'comment_status'    => $_POST['comment_status'],
            'ping_status'       => $_POST['ping_status'],
            'post_name'         => $_POST['post_name'],
            'post_status'       => $_POST['status'],
            'post_parent'       => $_POST['post_parent'],
            'menu_order'        => $_POST['menu_order'],
            'post_type'         => $_POST['post_type'],
            'post_password'     => $_POST['post_password'],
            'is_sticky'         => $_POST['is_sticky'],
            'attribute_author1' => $_POST['author1'],
            'attribute_author2' => $_POST['author2']
        );

        return $parameters;
    }

    /**
     * The main function for this plugin:
     *
     * Receives a POST request, and handles the file upload logic.
     */
    public function processUpload()
    {
        // Check if the nonce is valid.
        $this->checkNonce();

        $post = $this->extractParametersFromRequest( $_POST );

        // Try the Attachment URL first.
        // Fall back to the GUID if file not found at attachment url.
        // Should add a warning in that case, probably.
        $attachmentUrl = ! empty( $post['url'] ) ? $post['url'] : $post['guid'];

        try {

            $this->PreProcessAttachment( $post, $attachmentUrl );
            $upload = $this->fetchRemoteFile( $attachmentUrl, $post );

            $post['post_mime_type'] = $this->determineFileType( $upload );
            $post['post_author'] = $this->determineAuthor( $post );

            $postId = wp_insert_attachment( $post, $upload['file'] );
            $meta   = wp_generate_attachment_metadata( $postId, $upload['file'] );
            if(empty($meta)) {
                throw new \RuntimeException(sprintf(__( 'Could not extract meta information from image %s', 'bulk-att-xfer' ), $post['post_title']));
            }

            $metaResult = wp_update_attachment_metadata( $postId, $meta );

            $result = $this->fixAttachmentUrlsInContent( $post['url'], $upload['url'] );

            $this->renderSuccess(
                sprintf(
                    __('%s was uploaded successfully. URL was replaced in %d posts, %d post meta\'s and %d options.', 'bulk-att-xfer' ),
                    $post['post_title'],
                    $result['post'],
                    $result['meta'],
                    $result['options']
                )
            );

        } catch ( \Exception $e ) {
            $this->renderException( $e );
        }
    }

    protected function preProcessAttachment( $post, $url )
    {
        $wpdb     = $this->wpdb;
        $imported = $wpdb->get_results(
            $wpdb->prepare(
                "
				SELECT ID, post_date_gmt, guid
				FROM $wpdb->posts
				WHERE post_type = 'attachment'
					AND post_title = %s
				",
                $post['post_title']
            )
        );

        if ($imported) {
            foreach ($imported as $attachment) {

                if (
                    basename($url) == basename( $attachment->guid) &&
                    $post['post_date_gmt'] == $attachment->post_date_gmt
                ) {
                    $headers = wp_get_http( $url );
                    if (filesize( get_attached_file( $attachment->ID ) ) == $headers['content-length']) {
                        throw new \Exception(
                            sprintf(
                                __( 'File "%1$s" already exists', 'bulk-att-xfer' ),
                                esc_html( $post['post_title'] )
                            ), 419
                        );
                    }
                }
            }
        }
    }

    protected function fetchRemoteFile( $url, $post )
    {
        // extract the file name and extension from the url
        $file_name = basename( $url );
        // get placeholder file in the upload dir with a unique, sanitized filename
        $upload = wp_upload_bits( $file_name, 0, '', $post['post_date'] );
        if ($upload['error']) {
            throw new \RuntimeException('upload_dir_error', $upload['error']);
        }
        try {
            // fetch the remote url and write it to the placeholder file
            $headers = wp_get_http( $url, $upload['file'] );
            // request failed

            if ( ! $headers) {
                throw new \RuntimeException(
                    'import_file_error:' .
                    __( 'Remote server did not respond', 'bulk-att-xfer' )
                );
            }
            // make sure the fetch was successful
            if ($headers['response'] != '200') {
                throw new \RuntimeException(
                    'import_file_error:' .
                    sprintf( __( 'Remote server returned error response %1$d %2$s', 'bulk-att-xfer' ),
                        esc_html( $headers['response'] ), get_status_header_desc( $headers['response'] ) )
                );
            }

            $filesize = filesize( $upload['file'] );
            if ( ! isset( $headers['content-encoding'] ) || $headers['content-encoding'] !== 'gzip') {
                if (isset( $headers['content-length'] ) && $filesize != $headers['content-length']) {
                    throw new \RuntimeException(
                        'import_file_error:' .
                        __( 'Remote file is incorrect size', 'bulk-att-xfer' )
                    );
                }
            }

            if (0 == $filesize) {
                throw new \RuntimeException(
                    'import_file_error:' .
                    __( 'Zero size file downloaded', 'bulk-att-xfer' )
                );
            }
        } catch (\RuntimeException $e) {

            // Clean up the uploaded file.
            if(file_exists($upload['file'])) {
                unlink($upload['file']);
            }

            throw ($e);
        }

        return $upload;

    }

    protected function fixAttachmentUrlsInContent( $from_url, $to_url )
    {
        $result = array('post' => 0, 'meta' => 0, 'options' => 0);

        // remap urls in post_content
        $result['post'] = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
                $from_url, $to_url
            )
        );

        // Remap urls in post_meta
        $metaWithUrl = $this->wpdb->get_results(
            $this->wpdb->prepare("
SELECT meta_id, meta_value
FROM {$this->wpdb->postmeta}
WHERE meta_value LIKE %s
", '%'.$from_url.'%'), ARRAY_A);


        foreach($metaWithUrl as $metaRow) {

            $id = $metaRow['meta_id'];
            if(is_serialized($metaRow['meta_value'])) {
                $meta     = unserialize( $metaRow['meta_value'] );
                $replaced = $this->recursiveReplace( $from_url, $to_url, $meta );
                $replaced = serialize( $replaced );
            } else {
                $meta = $metaRow['meta_value'];
                $replaced = str_replace($from_url, $to_url, $meta);
            }

            $updateMetaQuery = $this->wpdb->prepare("
UPDATE {$this->wpdb->postmeta}
SET meta_value = %s
WHERE meta_id = %d", $replaced, (int) $id);
            $this->wpdb->query($updateMetaQuery);
            $result['meta']++;
        }

        // Remap urls in options table
        $optionsWithUrl = $this->wpdb->get_results(
            $this->wpdb->prepare("
SELECT option_id, option_value
FROM {$this->wpdb->options}
WHERE option_value LIKE %s
", '%'.$from_url.'%'), ARRAY_A);


        foreach($optionsWithUrl as $optionRow) {

            $id = $optionRow['meta_id'];
            if(is_serialized($optionRow['meta_value'])) {
                $option     = unserialize( $optionRow['meta_value'] );
                $replaced = $this->recursiveReplace( $from_url, $to_url, $option );
                $replaced = serialize( $replaced );
            } else {
                $option = $optionRow['meta_value'];
                $replaced = str_replace($from_url, $to_url, $option);
            }

            $updateMetaQuery = $this->wpdb->prepare("
UPDATE {$this->wpdb->options}
SET option_value = %s
WHERE option_id = %d", $replaced, (int) $id);
            $this->wpdb->query($updateMetaQuery);
            $result['options']++;
        }

        return $result;
    }

    protected function recursiveReplace( $find, $replace, $array )
    {
        if ( ! is_array( $array )) {
            return str_replace( $find, $replace, $array );
        }

        foreach ($array as &$value) {
            $value = $this->recursiveReplace( $find, $replace, $value );
        }

        return $array;
    }

    private function checkNonce()
    {
        // check nonce before doing anything else
        if (!\check_ajax_referer( 'import-attachment-plugin', false, false )) {
            $nonce_error = new WP_Error( 'nonce_error',
                __( 'Are you sure you want to do this?', 'bulk-att-xfer' ) );
            die( json_encode( array(
                'fatal'   => true,
                'type'    => 'error',
                'code'    => $nonce_error->get_error_code(),
                'message' => sprintf( __( 'The <a href="%1$s">security key</a> provided with this request is invalid. Is someone trying to trick you to upload something you don\'t want to? If you really meant to take this action, reload your browser window and try again. (<strong>%2$s</strong>: %3$s)',
                    'bulk-att-xfer' ), 'http://codex.wordpress.org/WordPress_Nonces',
                    $nonce_error->get_error_code(), $nonce_error->get_error_message() )
            ) ) );
        }
    }

    private function renderException( \Exception $e )
    {
        $fatal = ($e instanceof \RuntimeException);

        die( json_encode( array(
            'fatal'   => $fatal,
            'type'    => 'error',
            'code'    => $e->getCode(),
            'message' => $e->getMessage()
        ) ) );
    }

    private function determineFileType( $upload )
    {
        if ($info = wp_check_filetype( $upload['file'] )) {
            return $info['type'];
        } else {
            throw new \Exception(
                __( 'Invalid file type', 'bulk-att-xfer' )
            );
        }
    }

    private function determineAuthor( $post )
    {
        // Set author per user options.
        switch ($post['attribute_author1']) {
            case 1: // Attribute to current user.
                return (int) wp_get_current_user()->ID;
            case 2: // Attribute to user in import file.
                if ( ! username_exists( $post['post_author'] )) {
                    wp_create_user( $post['post_author'], wp_generate_password() );
                }
                return (int) username_exists( $post['post_author'] );
            case 3: // Attribute to selected user.
                return (int) $post['attribute_author2'];
            default:
                throw new \RuntimeException( "Invalid value for attribute_author1" );
        }
    }

    private function renderSuccess( $message )
    {
        die( json_encode( array(
            'fatal' => false,
            'type'  => 'updated',
            'message'  => $message
        ) ) );
    }
}

global $wpdb;
$importer = new BulkAttachmentTransfer( $wpdb );
BulkAttachmentTransfer::registerHooks( $importer );
