<?php

namespace NAPPS\Modules\Admin;

use NAPPS\Contracts\ModuleImplementation;

class AdminModule implements ModuleImplementation
{

    public function __construct()
    {
        if(is_admin()) {
            new Settings();
        }   
    }
}