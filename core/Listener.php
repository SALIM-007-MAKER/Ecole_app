<?php

namespace Core;

interface Listener
{
    public function handle(Event $event): void;
}
