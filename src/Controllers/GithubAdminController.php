<?php

namespace Tacraft\TcsGithubLogin\Controllers;

use Illuminate\Routing\Controller;
use Tacraft\TcsGithubLogin\Configuration;

class GithubAdminController extends Controller
{
    public function config()
    {
        return (new Configuration())->render();
    }
}
