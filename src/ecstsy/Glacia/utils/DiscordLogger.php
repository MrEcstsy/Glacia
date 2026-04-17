<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use bbo51dog\pmdiscord\connection\Webhook;
use bbo51dog\pmdiscord\element\Content;
use fenomeno\libWebhook\builder\EmbedBuilder;
use fenomeno\libWebhook\builder\WebhookMessageBuilder;
use fenomeno\libWebhook\WebhookPool;
use SOFe\AwaitGenerator\Await;

final class DiscordLogger {

    // "https://discord.com/api/webhooks/1407476694209794198/4tqlt3J2RY8E4CKcW4DMCKDEMokNE99tuo-pSYrT_Xgmf9VCZI9vAERDAfnJaIA9Trsa"

    public static function send(string $message, ?string $username = "Ethereal Hub"): void {
        Await::f2c(function() use($message, $username) {
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
                $result = yield from WebhookPool::getInstance()->asyncSubmit(
                    "https://discord.com/api/webhooks/1407476694209794198/4tqlt3J2RY8E4CKcW4DMCKDEMokNE99tuo-pSYrT_Xgmf9VCZI9vAERDAfnJaIA9Trsa",
                    $messageEmbed
                );
                echo "Sent with status {$result->status}\n";
            } catch (\Throwable $e) {
                echo "Failed to send webhook: {$e->getMessage()}\n";
            }
        });
    }
}