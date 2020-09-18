<?php
namespace App\Parse;

interface Parse
{
    public function get_coordinates($packages) : array;
}
