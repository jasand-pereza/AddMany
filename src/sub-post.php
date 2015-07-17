<?php

class SubPost extends \Taco\Post {
  final public function getFields() {
    return array(
      'field_assigned_to' => array('text')
    );
  }
  public function getPostTypeConfig() {
    return null;
  }
  public function getHierarchical() {
    return false;
  }
}