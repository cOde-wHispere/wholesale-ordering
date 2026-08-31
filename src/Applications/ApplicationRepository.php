<?php

namespace WholesaleOrdering\Applications;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**

* Persists and retrieves wholesale applications.
*
* V1 uses native WordPress user metadata and WooCommerce customer
* metadata. Application query support is implemented through the
* WordPress user/user-meta data model so Phase 5 administration can
* search, filter and paginate submitted applications without introducing
* a custom application table prematurely.
  */
  final class ApplicationRepository {

  private const META_STATUS                  = '_wholesale_ordering_status';
  private const META_COMPANY_NAME            = '_wholesale_ordering_company_name';
  private const META_TAX_NUMBER              = '_wholesale_ordering_tax_number';
  private const META_BUSINESS_REGISTRATION   = '_wholesale_ordering_business_registration_number';
  private const META_BUSINESS_TYPE           = '_wholesale_ordering_business_type';
  private const META_WEBSITE                 = '_wholesale_ordering_website';
  private const META_SUPPORTING_DOCUMENT     = '_wholesale_ordering_supporting_document_id';
  private const META_CONSENT_AT              = '_wholesale_ordering_consent_at';
  private const META_CONSENT_VERSION         = '_wholesale_ordering_consent_version';
  private const META_APPLIED_AT              = '_wholesale_ordering_applied_at';
  private const META_REVIEWED_AT             = '_wholesale_ordering_reviewed_at';
  private const META_REVIEWED_BY             = '_wholesale_ordering_reviewed_by';
  private const META_INTERNAL_NOTE           = '_wholesale_ordering_internal_note';

  /**

  * Default number of applications returned per page.
    */
    private const DEFAULT_PER_PAGE = 20;

  /**

  * Maximum number of applications returned per page.
    */
    private const MAX_PER_PAGE = 100;

  /**

  * Find an application by user ID.
  *
  * @param int $user_id User ID.
  *
  * @return WholesaleApplication|null
    */
    public function find_by_user_id(
    int $user_id
    ): ?WholesaleApplication {
    if ( $user_id <= 0 ) {
    return null;
    }

    $user = get_user_by(
    'id',
    $user_id
    );

    if ( ! $user ) {
    return null;
    }

    return new WholesaleApplication(
    $this->build_data( $user )
    );
    }

  /**

  * Determine whether a submitted application exists for a user.
  *
  * @param int $user_id User ID.
  *
  * @return bool
    */
    public function exists( int $user_id ): bool {
    $application = $this->find_by_user_id( $user_id );

    return null !== $application
    && $application->is_submitted();
    }

  /**

  * Find submitted applications.
  *
  * Supported query arguments:
  *
  * * status   string
  * * search   string
  * * page     int
  * * per_page int
  * * order    ASC|DESC
  *
  * Results are ordered by application submission timestamp by default.
  *
  * @param array<string, mixed> $args Query arguments.
  *
  * @return array<int, WholesaleApplication>
    */
    public function find_all(
    array $args = array()
    ): array {
    $query_args = $this->normalize_query_args(
    $args
    );

    $user_ids = $this->find_ids(
    $query_args
    );

    if ( empty( $user_ids ) ) {
    return array();
    }

    $applications = array();

    foreach ( $user_ids as $user_id ) {
    $application = $this->find_by_user_id(
    $user_id
    );

     /* 
      * The query is based on submitted application metadata, but 
      * re-read through the canonical repository/domain boundary. 
      * This also protects the result set if data was changed between 
      * the query and object construction. 
      */ 
     if ( 
         null !== $application 
         && $application->is_submitted() 
     ) { 
         $applications[] = $application; 
     } 

    }

    return $applications;
    }

  /**

  * Count submitted applications matching query criteria.
  *
  * @param array<string, mixed> $args Query arguments.
  *
  * @return int
    */
    public function count(
    array $args = array()
    ): int {
    global $wpdb;

    $query_args = $this->normalize_query_args(
    $args
    );

    $where  = array();
    $params = array();

    /*

    * Every application must have a submitted timestamp.
      */
      $where[] = 'applied_meta.meta_key = %s';
      $params[] = self::META_APPLIED_AT;

    $this->append_status_condition(
    $query_args['status'],
    $where,
    $params
    );

    $this->append_search_condition(
    $query_args['search'],
    $where,
    $params
    );

    $where_sql = implode(
    ' AND ',
    $where
    );

    $sql = "
    SELECT COUNT(DISTINCT users.ID)
    FROM {$wpdb->users} AS users
    INNER JOIN {$wpdb->usermeta} AS applied_meta
    ON applied_meta.user_id = users.ID
    LEFT JOIN {$wpdb->usermeta} AS status_meta
    ON status_meta.user_id = users.ID
    AND status_meta.meta_key = %s
    LEFT JOIN {$wpdb->usermeta} AS company_meta
    ON company_meta.user_id = users.ID
    AND company_meta.meta_key = %s
    LEFT JOIN {$wpdb->usermeta} AS tax_meta
    ON tax_meta.user_id = users.ID
    AND tax_meta.meta_key = %s
    LEFT JOIN {$wpdb->usermeta} AS registration_meta
    ON registration_meta.user_id = users.ID
    AND registration_meta.meta_key = %s
    WHERE {$where_sql}
    ";

    /*

    * The first four placeholders are fixed meta keys. The remaining
    * placeholders are query-condition values.
      */
      $params = array_merge(
      array(
      self::META_STATUS,
      self::META_COMPANY_NAME,
      self::META_TAX_NUMBER,
      self::META_BUSINESS_REGISTRATION,
      ),
      $params
      );

    $prepared = $wpdb->prepare(
    $sql,
    $params
    );

    if ( ! is_string( $prepared ) ) {
    return 0;
    }

    return max(
    0,
    (int) $wpdb->get_var( $prepared )
    );
    }

  /**

  * Return submitted application user IDs for a query.
  *
  * This is intentionally kept private so callers use domain objects
  * rather than raw user IDs.
  *
  * @param array<string, mixed> $args Normalized query arguments.
  *
  * @return array<int, int>
    */
    private function find_ids(
    array $args
    ): array {
    global $wpdb;

    $where  = array();
    $params = array();

    $where[] = 'applied_meta.meta_key = %s';
    $params[] = self::META_APPLIED_AT;

    $this->append_status_condition(
    $args['status'],
    $where,
    $params
    );

    $this->append_search_condition(
    $args['search'],
    $where,
    $params
    );

    $where_sql = implode(
    ' AND ',
    $where
    );

    $order = 'DESC' === $args['order']
    ? 'DESC'
    : 'ASC';

    $offset = $args['offset'];
    $limit  = $args['per_page'];

    /*

    * Application dates are stored as MySQL datetime strings by the
    * application service. Ordering by meta_value therefore gives the
    * expected chronological application ordering.
      */
      $sql = "
      SELECT DISTINCT users.ID
      FROM {$wpdb->users} AS users
      INNER JOIN {$wpdb->usermeta} AS applied_meta
      ON applied_meta.user_id = users.ID
      LEFT JOIN {$wpdb->usermeta} AS status_meta
      ON status_meta.user_id = users.ID
      AND status_meta.meta_key = %s
      LEFT JOIN {$wpdb->usermeta} AS company_meta
      ON company_meta.user_id = users.ID
      AND company_meta.meta_key = %s
      LEFT JOIN {$wpdb->usermeta} AS tax_meta
      ON tax_meta.user_id = users.ID
      AND tax_meta.meta_key = %s
      LEFT JOIN {$wpdb->usermeta} AS registration_meta
      ON registration_meta.user_id = users.ID
      AND registration_meta.meta_key = %s
      WHERE {$where_sql}
      ORDER BY applied_meta.meta_value {$order}, users.ID {$order}
      LIMIT %d OFFSET %d
      ";

    $params = array_merge(
    array(
    self::META_STATUS,
    self::META_COMPANY_NAME,
    self::META_TAX_NUMBER,
    self::META_BUSINESS_REGISTRATION,
    ),
    $params,
    array(
    $limit,
    $offset,
    )
    );

    $prepared = $wpdb->prepare(
    $sql,
    $params
    );

    if ( ! is_string( $prepared ) ) {
    return array();
    }

    $results = $wpdb->get_col(
    $prepared
    );

    if ( ! is_array( $results ) ) {
    return array();
    }

    return array_values(
    array_filter(
    array_map(
    'absint',
    $results
    )
    )
    );
    }

  /**

  * Add a status filter to a repository query.
  *
  * The query uses the persisted status only as a filtering mechanism.
  * Returned application objects still resolve status through
  * WholesaleStatus::get().
  *
  * @param string|null        $status  Requested status.
  * @param array<int, string> $where   SQL WHERE fragments.
  * @param array<int, mixed>  $params  SQL parameters.
  *
  * @return void
    */
    private function append_status_condition(
    ?string $status,
    array &$where,
    array &$params
    ): void {
    if (
    null === $status
    || '' === $status
    ) {
    return;
    }

    if ( ! Config::is_wholesale_status( $status ) ) {
    /*
    * An invalid status must produce no results rather than silently
    * returning all applications.
    */
    $where[] = '1 = 0';
    return;
    }

    /*

    * A submitted application is expected to have explicit status
    * metadata because mark_submitted() establishes pending status.
    *
    * Keep the condition explicit rather than using the canonical
    * default status because the filter is intended to represent the
    * stored administrative status.
      */
      $where[] = 'status_meta.meta_value = %s';
      $params[] = $status;
      }

  /**

  * Add applicant/business search conditions.
  *
  * Search covers the fields needed by Phase 5 administration:
  *
  * * name
  * * username
  * * email
  * * company
  * * tax number
  * * business registration number
  *
  * @param string            $search Search term.
  * @param array<int,string> $where  SQL WHERE fragments.
  * @param array<int,mixed>  $params SQL parameters.
  *
  * @return void
    */
    private function append_search_condition(
    string $search,
    array &$where,
    array &$params
    ): void {
    if ( '' === $search ) {
    return;
    }

    /*

    * Use the same wildcard value for each searchable field.
    * wpdb::prepare() safely escapes the value.
      */
      $like = '%' . $wpdb->esc_like( $search ) . '%';

    $where[] = '(
    users.user_login LIKE %s
    OR users.user_nicename LIKE %s
    OR users.user_email LIKE %s
    OR users.display_name LIKE %s
    OR users.user_url LIKE %s
    OR company_meta.meta_value LIKE %s
    OR tax_meta.meta_value LIKE %s
    OR registration_meta.meta_value LIKE %s
    )';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    }

  /**

  * Normalize repository query arguments.
  *
  * @param array<string, mixed> $args Raw query arguments.
  *
  * @return array{
  * 
    status:string, 
  * 
    search:string, 
  * 
    page:int, 
  * 
    per_page:int, 
  * 
    offset:int, 
  * 
    order:string 
  * }
    */
    private function normalize_query_args(
    array $args
    ): array {
    $status = isset( $args['status'] )
    ? sanitize_key(
    (string) $args['status']
    )
    : '';

    $search = isset( $args['search'] )
    ? sanitize_text_field(
    (string) $args['search']
    )
    : '';

    $page = isset( $args['page'] )
    ? absint( $args['page'] )
    : 1;

    if ( $page < 1 ) {
    $page = 1;
    }

    $per_page = isset( $args['per_page'] )
    ? absint( $args['per_page'] )
    : self::DEFAULT_PER_PAGE;

    if ( $per_page < 1 ) {
    $per_page = self::DEFAULT_PER_PAGE;
    }

    $per_page = min(
    $per_page,
    self::MAX_PER_PAGE
    );

    $order = isset( $args['order'] )
    ? strtoupper(
    sanitize_text_field(
    (string) $args['order']
    )
    )
    : 'DESC';

    if ( ! in_array(
    $order,
    array(
    'ASC',
    'DESC',
    ),
    true
    ) ) {
    $order = 'DESC';
    }

    return array(
    'status'   => $status,
    'search'   => $search,
    'page'     => $page,
    'per_page' => $per_page,
    'offset'   => ( $page - 1 ) * $per_page,
    'order'    => $order,
    );
    }

  /**

  * Save application profile/business data.

  *

  * Status and lifecycle transitions are intentionally handled by the

  * application service.

  *

  * @param int                  $user_id User ID.

  * @param array<string, mixed> $data    Validated data.

  *

  * @return bool
    */
    public function save(
    int $user_id,
    array $data
    ): bool {
    if (
    $user_id <= 0
    || ! get_user_by( 'id', $user_id )
    ) {
    return false;
    }

    $first_name = sanitize_text_field(
    (string) ( $data['first_name'] ?? '' )
    );
    $last_name = sanitize_text_field(
    (string) ( $data['last_name'] ?? '' )
    );

    $user_update = array(
    'ID'         => $user_id,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    );

    if ( false === wp_update_user( $user_update ) ) {
    $user = get_user_by( 'id', $user_id );

     if ( 
         ! $user 
         || (string) get_user_meta( $user_id, 'first_name', true ) !== $first_name 
         || (string) get_user_meta( $user_id, 'last_name', true ) !== $last_name 
     ) { 
         return false; 
     } 

    }

    $metadata = array(
    self::META_COMPANY_NAME => sanitize_text_field(
    (string) ( $data['company_name'] ?? '' )
    ),
    self::META_TAX_NUMBER => sanitize_text_field(
    (string) ( $data['tax_number'] ?? '' )
    ),
    self::META_BUSINESS_REGISTRATION => sanitize_text_field(
    (string) ( $data['business_registration_number'] ?? '' )
    ),
    self::META_BUSINESS_TYPE => sanitize_text_field(
    (string) ( $data['business_type'] ?? '' )
    ),
    self::META_WEBSITE => esc_url_raw(
    (string) ( $data['website'] ?? '' )
    ),
    self::META_SUPPORTING_DOCUMENT => absint(
    $data['supporting_document_id'] ?? 0
    ),
    self::META_CONSENT_AT => sanitize_text_field(
    (string) ( $data['consent_at'] ?? '' )
    ),
    self::META_CONSENT_VERSION => sanitize_text_field(
    (string) ( $data['consent_version'] ?? '' )
    ),
    );

    foreach ( $metadata as $key => $value ) {
    $result = update_user_meta(
    $user_id,
    $key,
    $value
    );

     /* 
      * WordPress returns false when the value is unchanged as well as 
      * when a write fails. Verify the authoritative value before 
      * declaring persistence failure. 
      */ 
     if ( 
         false === $result 
         && ! $this->meta_value_equals( 
             $user_id, 
             $key, 
             $value 
         ) 
     ) { 
         return false; 
     } 

    }

    if (
    ! $this->save_address(
    $user_id,
    'billing',
    $data['billing_address'] ?? array()
    )
    ) {
    return false;
    }

    if (
    isset( $data['delivery_address'] )
    && is_array( $data['delivery_address'] )
    && ! $this->save_address(
    $user_id,
    'shipping',
    $data['delivery_address']
    )
    ) {
    return false;
    }

    if ( isset( $data['phone'] ) ) {
    $phone = sanitize_text_field(
    (string) $data['phone']
    );

     $result = update_user_meta( 
         $user_id, 
         'billing_phone', 
         $phone 
     ); 

     if ( 
         false === $result 
         && ! $this->meta_value_equals( 
             $user_id, 
             'billing_phone', 
             $phone 
         ) 
     ) { 
         return false; 
     } 

    }

    return true;
    }

  /**

  * Mark an application as submitted.

  *

  * This operation also establishes pending wholesale status.

  *

  * @param int    $user_id   Applicant.

  * @param string $timestamp Submission timestamp.

  *

  * @return bool
    */
    public function mark_submitted(
    int $user_id,
    string $timestamp
    ): bool {
    if (
    $user_id <= 0
    || ! get_user_by( 'id', $user_id )
    ) {
    return false;
    }

    $timestamp = sanitize_text_field( $timestamp );

    if ( '' === $timestamp ) {
    return false;
    }

    $result = update_user_meta(
    $user_id,
    self::META_APPLIED_AT,
    $timestamp
    );

    /*

    * Do not treat update_user_meta(false) as an unconditional failure.
    * It can mean the requested value is already stored. The submission
    * marker is valid if the authoritative metadata contains the
    * requested timestamp after the operation.
      */
      if (
      false === $result
      && ! $this->meta_value_equals(
      $user_id,
      self::META_APPLIED_AT,
      $timestamp
      )
      ) {
      return false;
      }

    if (
    ! WholesaleStatus::set(
    $user_id,
    Config::STATUS_PENDING
    )
    ) {
    return false;
    }

    return $this->meta_value_equals(
    $user_id,
    self::META_APPLIED_AT,
    $timestamp
    );
    }

  /**

  * Record administrative review metadata.
  *
  * Status itself is managed by the service so that role/status
  * coordination remains in one place.
  *
  * @param int         $user_id
  * @param int         $reviewer_id
  * @param string      $timestamp
  * @param string|null $internal_note
  *
  * @return bool
    */
    public function record_review(
    int $user_id,
    int $reviewer_id,
    string $timestamp,
    ?string $internal_note = null
    ): bool {
    if (
    $user_id <= 0
    || $reviewer_id <= 0
    ) {
    return false;
    }

    if (
    ! get_user_by(
    'id',
    $user_id
    )
    || ! get_user_by(
    'id',
    $reviewer_id
    )
    ) {
    return false;
    }

    $operations = array(
    self::META_REVIEWED_AT => sanitize_text_field(
    $timestamp
    ),
    self::META_REVIEWED_BY => $reviewer_id,
    );

    if ( null !== $internal_note ) {
    $operations[ self::META_INTERNAL_NOTE ] =
    sanitize_textarea_field(
    $internal_note
    );
    }

    foreach ( $operations as $key => $value ) {
    $result = update_user_meta(
    $user_id,
    $key,
    $value
    );

     if ( 
         false === $result 
         && ! $this->meta_value_equals( 
             $user_id, 
             $key, 
             $value 
         ) 
     ) { 
         return false; 
     } 

    }

    return true;
    }

  /**

  * Clear review metadata.
  *
  * @param int $user_id User ID.
  *
  * @return bool
    */
    public function clear_review(
    int $user_id
    ): bool {
    if ( $user_id <= 0 ) {
    return false;
    }

    delete_user_meta(
    $user_id,
    self::META_REVIEWED_AT
    );

    delete_user_meta(
    $user_id,
    self::META_REVIEWED_BY
    );

    delete_user_meta(
    $user_id,
    self::META_INTERNAL_NOTE
    );

    return true;
    }

  /**

  * Build domain data from a WordPress user.
  *
  * @param \WP_User $user User object.
  *
  * @return array<string, mixed>
    */
    private function build_data(
    \WP_User $user
    ): array {
    $user_id = (int) $user->ID;

    return array(
    'user_id' => $user_id,
    'status'  => WholesaleStatus::get(
    $user_id
    ),

     'first_name' => (string) get_user_meta( 
         $user_id, 
         'first_name', 
         true 
     ), 
     'last_name' => (string) get_user_meta( 
         $user_id, 
         'last_name', 
         true 
     ), 
     'email' => (string) $user->user_email, 

     'company_name' => (string) get_user_meta( 
         $user_id, 
         self::META_COMPANY_NAME, 
         true 
     ), 
     'phone' => (string) get_user_meta( 
         $user_id, 
         'billing_phone', 
         true 
     ), 

     'billing_address' => $this->get_address( 
         $user_id, 
         'billing' 
     ), 
     'delivery_address' => $this->get_address( 
         $user_id, 
         'shipping' 
     ), 

     'tax_number' => (string) get_user_meta( 
         $user_id, 
         self::META_TAX_NUMBER, 
         true 
     ), 
     'business_registration_number' => (string) get_user_meta( 
         $user_id, 
         self::META_BUSINESS_REGISTRATION, 
         true 
     ), 
     'business_type' => (string) get_user_meta( 
         $user_id, 
         self::META_BUSINESS_TYPE, 
         true 
     ), 
     'website' => (string) get_user_meta( 
         $user_id, 
         self::META_WEBSITE, 
         true 
     ), 
     'supporting_document_id' => (int) get_user_meta( 
         $user_id, 
         self::META_SUPPORTING_DOCUMENT, 
         true 
     ), 
     'consent_at' => (string) get_user_meta( 
         $user_id, 
         self::META_CONSENT_AT, 
         true 
     ), 
     'consent_version' => (string) get_user_meta( 
         $user_id, 
         self::META_CONSENT_VERSION, 
         true 
     ), 
     'applied_at' => (string) get_user_meta( 
         $user_id, 
         self::META_APPLIED_AT, 
         true 
     ), 
     'reviewed_at' => (string) get_user_meta( 
         $user_id, 
         self::META_REVIEWED_AT, 
         true 
     ), 
     'reviewed_by' => (int) get_user_meta( 
         $user_id, 
         self::META_REVIEWED_BY, 
         true 
     ), 
     'internal_note' => (string) get_user_meta( 
         $user_id, 
         self::META_INTERNAL_NOTE, 
         true 
     ), 

    );
    }

  /**

  * Save a WooCommerce address.
  *
  * @param int                  $user_id User ID.
  * @param string               $type    Address type.
  * @param array<string, mixed> $address Address data.
  *
  * @return bool
    */
    private function save_address(
    int $user_id,
    string $type,
    array $address
    ): bool {
    $allowed = array(
    'first_name',
    'last_name',
    'company',
    'address_1',
    'address_2',
    'city',
    'state',
    'postcode',
    'country',
    );

    foreach ( $allowed as $field ) {
    if ( ! array_key_exists(
    $field,
    $address
    ) ) {
    continue;
    }

     $meta_key = $type . '_' . $field; 
     $value = sanitize_text_field( 
         (string) $address[ $field ] 
     ); 

     $result = update_user_meta( 
         $user_id, 
         $meta_key, 
         $value 
     ); 

     if ( 
         false === $result 
         && ! $this->meta_value_equals( 
             $user_id, 
             $meta_key, 
             $value 
         ) 
     ) { 
         return false; 
     } 

    }

    return true;
    }

  /**

  * Verify that a metadata write resulted in the expected value.
  *
  * WordPress update_user_meta() may return false when the stored value is
  * already equal to the requested value, so callers must verify state
  * rather than using the return value as a simple success/failure flag.
  *
  * @param mixed $expected Expected scalar value.
    */
    private function meta_value_equals(
    int $user_id,
    string $key,
    $expected
    ): bool {
    return (string) get_user_meta(
    $user_id,
    $key,
    true
    ) === (string) $expected;
    }

  /**

  * Retrieve a WooCommerce address.
  *
  * @param int    $user_id User ID.
  * @param string $type    Address type.
  *
  * @return array<string, string>
    */
    private function get_address(
    int $user_id,
    string $type
    ): array {
    $fields = array(
    'first_name',
    'last_name',
    'company',
    'address_1',
    'address_2',
    'city',
    'state',
    'postcode',
    'country',
    );

    $address = array();

    foreach ( $fields as $field ) {
    $address[ $field ] = (string) get_user_meta(
    $user_id,
    $type . '_' . $field,
    true
    );
    }

    return $address;
    }
    }
