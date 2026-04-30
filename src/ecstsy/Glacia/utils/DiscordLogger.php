<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use fenomeno\libWebhook\builder\EmbedBuilder;
use fenomeno\libWebhook\builder\WebhookMessageBuilder;
use fenomeno\libWebhook\WebhookPool;
use SOFe\AwaitGenerator\Await;

final class DiscordLogger {

    public static function send(string $message, ?string $username = "Ethereal Hub"): void {
        $loader = Loader::getInstance();
        $config = GeneralUtils::getConfiguration($loader, "config.yml");
        $enabled = (bool) ($config?->getNested("audit-log.staff-discord-enabled", false) ?? false);
        if (!$enabled) {
            return;
        }
        $webhookUrl = trim((string) ($config?->getNested("audit-log.staff-discord-webhook", "") ?? ""));
        if ($webhookUrl === "") {
            return;
        }

        Await::f2c(function() use($loader, $message, $username, $webhookUrl) {
            $embed = EmbedBuilder::create()
                ->title("Glacia Factions")
                ->description($message)
                ->color(0x9acced)
                ->thumbnail("https://i.imgur.com/FBtlgCp.png")
                ->build();

            $messageEmbed = WebhookMessageBuilder::create()
                ->embeds([$embed])
                ->username($username)
                ->build();

            try {
                $result = yield from WebhookPool::getInstance()->asyncSubmit($webhookUrl, $messageEmbed);
                if (!$result->ok) {
                    $loader->getLogger()->debug("Discord webhook returned non-OK status: " . $result->status);
                }
            } catch (\Throwable $e) {
                $loader->getLogger()->warning("Failed to send Discord webhook: " . $e->getMessage());
            }
        });
    }
}
