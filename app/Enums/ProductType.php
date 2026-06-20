<?php

namespace App\Enums;

enum ProductType: string
{
    case SharedHosting   = 'shared_hosting';
    case Vps             = 'vps';
    case DedicatedServer = 'dedicated_server';
    case Domain          = 'domain';
    case Ssl             = 'ssl';

    /**
     * Infer type from WHMCS product fields.
     * Priority: module name (most reliable) → WHMCS type field → product name keywords.
     */
    public static function fromWhmcs(string $type, string $name = '', string $module = ''): self
    {
        $moduleL = strtolower($module);
        $nameL   = strtolower($name);

        // Detect VPS by hosting module name
        foreach (['virtfusion', 'proxmox', 'solusvm', 'openvz', 'hyperv', 'kvm', 'virtualizor'] as $m) {
            if (str_contains($moduleL, $m)) {
                return self::Vps;
            }
        }

        // Detect SSL by module name
        foreach (['comodo', 'sectigo', 'letsencrypt', 'sslstore', 'gogetssl'] as $m) {
            if (str_contains($moduleL, $m)) {
                return self::Ssl;
            }
        }

        return match (strtolower($type)) {
            'hostingaccount' => str_contains($nameL, 'vps') ? self::Vps : self::SharedHosting,
            'server'         => str_contains($nameL, 'vps') ? self::Vps : self::DedicatedServer,
            'other'          => self::fromName($nameL),
            default          => self::SharedHosting,
        };
    }

    private static function fromName(string $name): self
    {
        if (str_contains($name, 'vps') || str_contains($name, 'virtual private')) {
            return self::Vps;
        }
        if (str_contains($name, 'dedicated') || str_contains($name, 'bare metal')) {
            return self::DedicatedServer;
        }
        if (str_contains($name, 'ssl') || str_contains($name, 'certificate')) {
            return self::Ssl;
        }
        if (str_contains($name, 'domain')) {
            return self::Domain;
        }
        return self::SharedHosting;
    }
}
