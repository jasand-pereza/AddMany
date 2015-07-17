<?php
/*
 * AddMany
 * Description: AddMany is a TacoWordPress add-on that allows you to
 * create an arbitrary number of fields for custom posts in the WordPress admin.
 */

namespace JasandPereza;
use \Taco\Util\Arr;
use \Taco\Util\Collection;

class AddMany {
  const VERSION = '001';
  public static $field_definitions = [];
  public static $wp_tiny_mce_settings = null;
  public static $path_url = null;
  public function init() {
    if(is_null(self::$path_url)) {
      self::$path_url = '/'.strstr(dirname(__FILE__), 'vendor');
    }
    wp_register_script(
      'requirejs',
      '/addons/addmany/Frontend/js/require.js',
      false,
      self::VERSION,
      true);
    wp_enqueue_script('requirejs');

    wp_register_script(
      'addmany_config',
      '/addons/addmany/Frontend/js/config.js',
      false,
      self::VERSION,
      true);
    wp_enqueue_script('addmany_config');

    wp_register_style(
      'addmany',
      '/addons/addmany/Frontend/css/addmany.css',
      false,
      self::VERSION
    );
    wp_enqueue_style('addmany');

    self::loadFieldDefinitions();
    
    wp_localize_script(
      'addmany_config',
      'field_definitions',
      self::$field_definitions
    );

    // Allow this plugin to access the Wordpress TinyMCE settings
    wp_localize_script(
      'addmany_config',
      'wp_tiny_mce_settings',
      self::$wp_tiny_mce_settings
    );

    // Allow this script to use admin-ajax.php
    wp_localize_script(
      'addmany_config',
      'AJAXSubmit',
      array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'AJAXSubmit_nonce' => wp_create_nonce(
          'AJAXSubmit-posting'
        )
      )
    );
  }

  public function loadFieldDefinitions() {
    global $post;
    if(!$post) {
      return false;
    }
    if(!array_key_exists('post_type', $post)) {
      return false;
    }
    $class = str_replace(
      ' ',
      '',
      ucwords(
        str_replace(
          \Taco\Base::SEPARATOR,
          ' ',
          $post->post_type
        )
      )
    );

    if(class_exists($class)) {
      $custom_post = \Taco\Post\Factory::create($post);
      $fields = $custom_post->getFields();
      foreach($fields as $k => $v) {
        if(!array_key_exists('fields', $v)) continue;
        self::$field_definitions[$k] = $v['fields'];
      }
    }
  }


  public static function createNewSubPost($post_data) {
    if(!array_key_exists('field_assigned_to', $post_data)) {
      return false;
    }
    $subpost = new \SubPost;
    $subpost->set('post_title', 'subpost');
    $subpost->set('post_parent', $post_data['parent_id']);
    $subpost->set(
      'field_assigned_to',
      trim($post_data['field_assigned_to'])
    );
    
    $id = $subpost->save();
    $response = json_encode(
      array(
        'success' => true,
        'post_id' => $id,
        'fields' => self::getFieldDefinitionKeyAttribs(
          $post_data['field_assigned_to'],
          $post_data['parent_id']
        )
      )
    );
    header('Content-Type: application/json');
    echo $response;
    exit;
  }

  public static function AJAXSubmit() {
    if(array_key_exists('get_by', $_POST)
      && array_key_exists('field_assigned_to', $_POST)
      && array_key_exists('parent_id', $_POST)
    ) {
      return self::getAJAXSubPosts(
        $_POST['field_assigned_to'],
        $_POST['parent_id']
      );
    }
    return self::createNewSubPost($_POST);
  }

  // This is if a user creates new sub-posts but then leaves the page
  // without hitting the publish or update button
  public static function removeAbandonedPosts() {
    $sub_posts = \SubPost::getWhere(array('post_parent' => 0));
    foreach($sub_posts as $sp) {
      wp_delete_post($sp->ID, true);
    }
  }

  private static function getSubPostsSafe($field_assigned_to, $parent_id) {
    global $wpdb;
    $query = sprintf(
      "SELECT ID, post_content, post_title from %s
      LEFT JOIN %s on post_id = ID
      WHERE meta_key = 'field_assigned_to'
      AND meta_value = '%s'
      AND post_parent = %d",
      $wpdb->posts,
      $wpdb->postmeta,
      $field_assigned_to,
      $parent_id
    );
    return $wpdb->get_results($query, OBJECT);
  }

  private static function getFieldDefinitionKeys($field_assigned_to, $parent_id) {
    return array_keys(
      \Taco\Post\Factory::create($parent_id)
        ->getFields()[$field_assigned_to]['fields']
    );
  }

  private static function getFieldDefinitionKeyAttribs($field_assigned_to, $parent_id) {
    
    $record_fields = \Taco\Post\Factory::create($parent_id)
      ->getFields()[$field_assigned_to]['fields'];
    $fields_attribs = [];

    foreach($record_fields as $k => $attribs) {
      foreach($attribs as $a => $v) {
        if($a == 'value') continue;
        $fields_attribs[$k][$a] = $v;
      }
    }
    return $fields_attribs;
  }

  private static function getAJAXSubPosts($field_assigned_to, $parent_id) {
    $fields_attribs = self::getFieldDefinitionKeyAttribs($field_assigned_to, $parent_id);
    $subfields = self::getFieldDefinitionKeys($field_assigned_to, $parent_id);

    $array_ids = array_map(function($id) {
      return trim((int) $id);
    }, $_POST['array_ids']);
    
    // remove any sub-posts without parents
    self::removeAbandonedPosts();
    $records = \Taco\Post\Factory::createMultiple($array_ids);

    // filter out the fields we don't need
    $filtered = array_map(function($subpost) use ($subfields, $fields_attribs) {
      $post_title = $subpost->post_title;
      if(isset($subpost->post_title) && preg_match('/[&\']{1,}/', $subpost->post_title)) {
        $post_title = stripslashes($subpost->post_title);
      }
      $array_fields_values = [];
      foreach($subfields as $key) {
        $array_fields_values[$key] = array(
          'value' => $subpost->get($key),
          'attribs' => $fields_attribs[$key]
        );
      }
      return array_merge(
        array('fields' => $array_fields_values),
        array(
          'post_id' => $subpost->ID,
        )
      );
    }, $records);
  
    header('Content-Type: application/json');
    echo json_encode(
      array(
        'success' => true,
        'posts' => $filtered
      )
    );
    exit;
  }

  public static function updateSubPosts($post_id, $fields_values, $post_parent=null) {
    $post_id = trim(preg_replace('/\D/', '', $post_id));
    $subpost = \SubPost::find($post_id);
    
    if(wp_is_post_revision($post_parent)) return false;
    
    foreach($fields_values as $k => $v) {
      update_post_meta($post_id, $k, $v);
    }
    remove_action('save_post', 'AddMany::saveAll');

    return true;
  }

  private static function areThereDeletedIds() {
    if(!array_key_exists('addmany_deleted_ids', $_POST)) return false;
    if(!strlen('addmany_deleted_ids')) return false;
    return true;
  }

  private static function deleteSubPosts($string_ids) {
    $ids = explode(',', $string_ids);
    if(!Arr::iterable($ids)) return false;
    foreach($ids as $id) {
      wp_delete_post((int) $id, true);
    }
    return true;
  }

  public static function saveAll($post_id) {
    if(self::areThereDeletedIds()) {
      self::deleteSubPosts($_POST['addmany_deleted_ids']);
    }
    if(!array_key_exists('subposts', $_POST)) return false;

    $source = $_POST;
    $subposts = $_POST['subposts'];

    if(!Arr::iterable($subposts)) return false;
    foreach($subposts as $record) {
      if(!Arr::iterable($record)) continue;
      foreach($record as $k => $v) {
        self::updateSubPosts(
          $k,
          $v,
          $post_id
        );
      }
    }
    return true;
  }

  public function getSubPosts($fieldname, $post_id) {

    $record = \Taco\Post::find($post_id);

    $field_ids = explode(',', $record->get($fieldname));
    $subposts = self::getSubPostsSafe($fieldname, $post_id);
    $subpost_ids = Collection::pluck($subposts, 'ID');
    
    $filtered = [];
    foreach($field_ids as $fid) {
      if(!in_array($fid, $subpost_ids)) continue;
      $filtered[$fid] = \Taco\Post::find($fid);
    }
    return $filtered;
  }
}