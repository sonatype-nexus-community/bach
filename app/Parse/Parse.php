<?php
namespace App\Parse;

interface Parse
{
    public function getCoordinates($packages) : array;
}
