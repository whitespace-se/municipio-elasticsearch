<?php
namespace MUNICIPIO_ELASTICSEARCH\Admin\Settings;

class Query {
  public function __construct() {
    add_action('plugins_loaded', array($this, 'init'));

    add_filter('acf/load_field/name=query_indices', array($this, 'indices'));
  }

  public function init() {
    if (function_exists('acf_add_options_page')) {
      acf_add_options_sub_page(array(
        'page_title' => 'Query',
        'menu_title' => 'Query',
        'menu_slug' => 'municipio-elasticsearch-query',
        'parent_slug' => Main::$MENU_SLUG,
      ));
    }
  }

  public function indices($field) {
    $indices = $this->remote_request_helper('_cat/indices?format=json');

    $field['choices'] = array();

    if (is_array($indices)) {
      foreach ($indices as $index) {
        $field['choices'][$index['index']] = $index['index'];
      }

      ksort($field['choices']);
    }

    return $field;
  }

  protected function remote_request_helper($path) {
    $request = \ElasticPress\Elasticsearch::factory()->remote_request($path);

    if (is_wp_error($request) || empty($request)) {
      return false;
    }

    $body = wp_remote_retrieve_body($request);

    return json_decode($body, true);
  }
}