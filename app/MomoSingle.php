<?php

class MomoSingle
{
    public static function single($value)
    {
        return 'Chat_' . md5((string) $value);
    }
}
