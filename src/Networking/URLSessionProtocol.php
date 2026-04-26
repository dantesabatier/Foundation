<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
interface URLSessionProtocol
{
    public function add(EasyHandle $handle): void;

    public function remove(EasyHandle $handle): void;

    public function behaviour(URLSessionTask $task): TaskBehaviour;
}
