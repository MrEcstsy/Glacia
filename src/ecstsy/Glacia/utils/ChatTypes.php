<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

final class ChatTypes {

    public const ALL = 'all';
    public const FACTION = 'faction';
    public const ALLY = 'ally';
    public const STAFF = 'staff';

    public const FORMAT_FACTION = "&r&a{fac_rank}{name}&2: &a{message}";
    public const FORMAT_ALLY = "&r&d{fac_rank}{fac_name}&5 {name}: &d{message}";
    public const FORMAT_STAFF   = "&r&c[STAFF] &f{name}&4: &f{message}";
}