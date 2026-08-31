<?php

namespace WholesaleOrdering\Security;

use WholesaleOrdering\Infrastructure\Logger;

/**
 * Secure boundary for wholesale supporting documents.
 *
 * Supporting documents are deliberately NOT stored in wp-content/uploads.
 * The stored application reference is an attachment post ID used only as an
 * opaque document record. The actual bytes live outside the public web root.
 */
final class DocumentSecurity {

    private const META_PATH = '_wholesale_ordering_secure_path';
    private const META_OWNER = '_wholesale_ordering_secure_owner';
    private const META_ORIGINAL_NAME = '_wholesale_ordering_secure_name';
    private const META_MIME = '_wholesale_ordering_secure_mime';
    private const META_SIZE = '_wholesale_ordering_secure_size';
    private const META_SHA256 = '_wholesale_ordering_secure_sha256';

    private const DOWNLOAD_ACTION = 'wholesale_ordering_secure_document';
    private const NONCE_ACTION = 'wholesale_ordering_secure_document';
    private const NONCE_FIELD = '_wholesale_ordering_secure_document_nonce';

    private const MAX_BYTES = 5242880; // 5 MiB.

    /** @var array<string,string> */
    private const ALLOWED_MIMES = array(
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    );

    public static function register(): void {
        add_action(
            'admin_post_' . self::DOWNLOAD_ACTION,
            array( self::class, 'handle_download' )
        );
    }

    /**
     * Validate a PHP upload without placing it in the public media library.
     *
     * @param array<string,mixed> $file
     * @return true|\WP_Error
     */
    public static function validate_upload( array $file ) {
        if ( ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) ) {
            return new \WP_Error( 'document_upload_invalid', __( 'The supporting document upload is invalid.', 'wholesale-ordering' ) );
        }

        if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
            return true;
        }

        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new \WP_Error( 'document_upload_failed', __( 'The supporting document could not be uploaded.', 'wholesale-ordering' ) );
        }

        $size = (int) $file['size'];
        if ( $size <= 0 || $size > self::MAX_BYTES ) {
            return new \WP_Error( 'document_size_invalid', __( 'The supporting document must be between 1 byte and 5 MB.', 'wholesale-ordering' ) );
        }

        $tmp = (string) $file['tmp_name'];
        if ( ! is_uploaded_file( $tmp ) || ! is_readable( $tmp ) ) {
            return new \WP_Error( 'document_tmp_invalid', __( 'The supporting document upload could not be verified.', 'wholesale-ordering' ) );
        }

        $check = wp_check_filetype_and_ext( $tmp, (string) $file['name'] );
        $mime = isset( $check['type'] ) ? (string) $check['type'] : '';
        if ( ! isset( self::ALLOWED_MIMES[ $mime ] ) ) {
            return new \WP_Error( 'document_type_invalid', __( 'Only PDF, JPG/JPEG and PNG supporting documents are allowed.', 'wholesale-ordering' ) );
        }

        $finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : false;
        if ( $finfo ) {
            $detected = (string) finfo_file( $finfo, $tmp );
            finfo_close( $finfo );
            if ( $detected !== $mime ) {
                return new \WP_Error( 'document_mime_mismatch', __( 'The uploaded document MIME type could not be verified.', 'wholesale-ordering' ) );
            }
        }

        /**
         * Optional malware scanner boundary. A security plugin/service may
         * return a WP_Error to reject the upload. No scanner is assumed.
         */
        $scan = apply_filters( 'wholesale_ordering_scan_document', true, $tmp, $mime );
        if ( is_wp_error( $scan ) ) {
            return $scan;
        }
        if ( false === $scan ) {
            return new \WP_Error( 'document_scan_rejected', __( 'The supporting document was rejected by the security scanner.', 'wholesale-ordering' ) );
        }

        return true;
    }

    /**
     * Store a validated upload outside the public web root and create an
     * opaque attachment record for the existing application metadata model.
     *
     * @param int                 $owner_user_id
     * @param array<string,mixed> $file
     * @return int|\WP_Error
     */
    public static function store_upload( int $owner_user_id, array $file ) {
        if ( $owner_user_id <= 0 ) {
            return new \WP_Error( 'document_owner_invalid', __( 'A valid document owner is required.', 'wholesale-ordering' ) );
        }

        $validation = self::validate_upload( $file );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
            return 0;
        }

        $root = self::storage_root();
        if ( ! self::ensure_storage_root( $root ) ) {
            return new \WP_Error( 'document_storage_unavailable', __( 'Secure document storage is unavailable.', 'wholesale-ordering' ) );
        }

        $mime = (string) wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] )['type'];
        $extension = self::ALLOWED_MIMES[ $mime ] ?? 'bin';
        $token = wp_generate_uuid4();
        $directory = trailingslashit( $root ) . substr( hash( 'sha256', $token ), 0, 2 );
        wp_mkdir_p( $directory );
        $filename = hash( 'sha256', $token . wp_salt( 'auth' ) ) . '.' . $extension;
        $destination = trailingslashit( $directory ) . $filename;

        if ( ! @move_uploaded_file( (string) $file['tmp_name'], $destination ) ) {
            return new \WP_Error( 'document_store_failed', __( 'The supporting document could not be stored securely.', 'wholesale-ordering' ) );
        }

        @chmod( $destination, 0600 );

        $attachment_id = wp_insert_attachment(
            array(
                'post_title'     => sanitize_text_field( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ),
                'post_status'    => 'private',
                'post_mime_type' => $mime,
                'post_parent'    => 0,
            ),
            '',
            0,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $destination );
            return $attachment_id;
        }

        $metadata = array(
            self::META_PATH          => $destination,
            self::META_OWNER         => $owner_user_id,
            self::META_ORIGINAL_NAME => sanitize_file_name( (string) $file['name'] ),
            self::META_MIME          => $mime,
            self::META_SIZE          => (int) filesize( $destination ),
            self::META_SHA256        => hash_file( 'sha256', $destination ),
        );

        foreach ( $metadata as $key => $value ) {
            update_post_meta( (int) $attachment_id, $key, $value );
        }

        return (int) $attachment_id;
    }

    /**
     * Build a capability-protected admin download URL. This is never a media
     * URL and does not reveal the filesystem path.
     */
    public static function download_url( int $document_id ): string {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action'      => self::DOWNLOAD_ACTION,
                    'document_id' => $document_id,
                ),
                admin_url( 'admin-post.php' )
            ),
            self::NONCE_ACTION,
            self::NONCE_FIELD
        );
    }

    public static function can_download( int $document_id, int $user_id = 0 ): bool {
        if ( $document_id <= 0 ) {
            return false;
        }

        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $user_id <= 0 ) {
            return false;
        }

        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        return $user_id === (int) get_post_meta( $document_id, self::META_OWNER, true );
    }

    /**
     * Migrate an old public WordPress attachment into the secure boundary.
     * The old attachment post remains only as the opaque record; its public
     * file is removed from uploads after the secure copy succeeds.
     */
    public static function migrate_legacy_attachment( int $document_id, int $owner_user_id ): bool {
        if ( $document_id <= 0 || $owner_user_id <= 0 ) {
            return false;
        }

        if ( self::is_secure_record( $document_id ) ) {
            return true;
        }

        $attachment = get_post( $document_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            return false;
        }

        $source = get_attached_file( $document_id );
        if ( ! $source || ! is_file( $source ) || ! is_readable( $source ) ) {
            return false;
        }

        $mime = get_post_mime_type( $document_id );
        if ( ! isset( self::ALLOWED_MIMES[ $mime ] ) ) {
            return false;
        }

        if ( filesize( $source ) > self::MAX_BYTES ) {
            return false;
        }

        $root = self::storage_root();
        if ( ! self::ensure_storage_root( $root ) ) {
            return false;
        }

        $token = wp_generate_uuid4();
        $directory = trailingslashit( $root ) . substr( hash( 'sha256', $token ), 0, 2 );
        wp_mkdir_p( $directory );
        $destination = trailingslashit( $directory ) . hash( 'sha256', $token . wp_salt( 'auth' ) ) . '.' . self::ALLOWED_MIMES[ $mime ];

        if ( ! @rename( $source, $destination ) ) {
            if ( ! @copy( $source, $destination ) ) {
                return false;
            }
            @unlink( $source );
        }
        @chmod( $destination, 0600 );

        update_post_meta( $document_id, self::META_PATH, $destination );
        update_post_meta( $document_id, self::META_OWNER, $owner_user_id );
        update_post_meta( $document_id, self::META_ORIGINAL_NAME, sanitize_file_name( basename( $source ) ) );
        update_post_meta( $document_id, self::META_MIME, $mime );
        update_post_meta( $document_id, self::META_SIZE, (int) filesize( $destination ) );
        update_post_meta( $document_id, self::META_SHA256, hash_file( 'sha256', $destination ) );

        // Prevent WordPress from continuing to expose the old media URL/file.
        delete_post_meta( $document_id, '_wp_attached_file' );
        delete_post_meta( $document_id, '_wp_attachment_metadata' );
        wp_update_post(
            array(
                'ID'          => $document_id,
                'post_status' => 'private',
            )
        );

        return true;
    }

    /** @return string */
    public static function document_name( int $document_id ): string {
        $name = (string) get_post_meta( $document_id, self::META_ORIGINAL_NAME, true );
        if ( '' !== $name ) {
            return $name;
        }
        return (string) get_the_title( $document_id );
    }

    /** @return bool */
    private static function is_secure_record( int $document_id ): bool {
        return '' !== (string) get_post_meta( $document_id, self::META_PATH, true );
    }

    private static function storage_root(): string {
        return trailingslashit( dirname( ABSPATH ) ) . 'wholesale-ordering-secure-documents';
    }

    private static function ensure_storage_root( string $root ): bool {
        if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
            return false;
        }

        $index = trailingslashit( $root ) . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }

        return is_writable( $root );
    }

    public static function handle_download(): void {
        $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;

        if ( ! isset( $_GET[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            wp_die( esc_html__( 'Invalid document security token.', 'wholesale-ordering' ), esc_html__( 'Access denied', 'wholesale-ordering' ), array( 'response' => 403 ) );
        }

        if ( ! self::can_download( $document_id ) ) {
            wp_die( esc_html__( 'You do not have permission to access this document.', 'wholesale-ordering' ), esc_html__( 'Access denied', 'wholesale-ordering' ), array( 'response' => 403 ) );
        }

        $path = (string) get_post_meta( $document_id, self::META_PATH, true );
        $root = realpath( self::storage_root() );
        $real = $path && file_exists( $path ) ? realpath( $path ) : false;

        if ( ! $root || ! $real || 0 !== strpos( $real, trailingslashit( $root ) ) || ! is_readable( $real ) ) {
            wp_die( esc_html__( 'The requested document is unavailable.', 'wholesale-ordering' ), esc_html__( 'Document unavailable', 'wholesale-ordering' ), array( 'response' => 404 ) );
        }

        $mime = (string) get_post_meta( $document_id, self::META_MIME, true );
        $name = self::document_name( $document_id );

        nocache_headers();
        header( 'Content-Type: ' . ( $mime ?: 'application/octet-stream' ) );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
        header( 'Content-Length: ' . (string) filesize( $real ) );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Security-Policy: sandbox' );
        readfile( $real );
        exit;
    }

    /**
     * Migrate all application document references currently pointing at
     * public media. Called once from the schema migration.
     */
    public static function migrate_legacy_documents(): void {
        $users = get_users(
            array(
                'fields'     => array( 'ID' ),
                'meta_key'   => '_wholesale_ordering_supporting_document_id',
                'number'     => -1,
            )
        );

        foreach ( $users as $user ) {
            $document_id = (int) get_user_meta( (int) $user->ID, '_wholesale_ordering_supporting_document_id', true );
            if ( $document_id > 0 && ! self::migrate_legacy_attachment( $document_id, (int) $user->ID ) ) {
                Logger::warning( 'Unable to migrate a legacy wholesale supporting document.', array( 'document_id' => $document_id, 'user_id' => (int) $user->ID ) );
            }
        }
    }
}
