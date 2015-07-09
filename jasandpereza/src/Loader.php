<?php
namespace Jasandpereza;
class Loader
{
    public static function init()
    {
        add_action('admin_head', '\JasandPereza\AddMany::init');
       
    }
}
