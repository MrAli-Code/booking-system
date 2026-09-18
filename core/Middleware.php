<?php
namespace BBS\Core;

abstract class Middleware
{
    abstract public function handle(Request $request, callable $next): mixed;
}
