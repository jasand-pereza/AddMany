<?php

trait subPostParentMethods {
  public static $sub_posts_definitions = [];

  public function getSubPosts($fieldname) {
    
    $field_ids = explode(',', $this->get($fieldname));
    $subposts = SubPost::getWhere(
      array(
        'post_parent' => $this->ID
      )
    );
    
    $filtered = [];
    foreach($subposts as $sp) {
      if(!in_array($sp->ID, $field_ids)) continue;
      $filtered[$sp->ID] = $sp;
    }
    return $filtered;
  }
}