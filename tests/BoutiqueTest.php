<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Module Boutique côté commerçant : produits, stock, commandes, clients et paramètres.
 */
final class BoutiqueTest extends IntegrationTestCase
{
    public function testOtherSectorsCannotOpenTheBoutique(): void
    {
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $this->tenant(Sector::RESERVATION));

        foreach (['/boutique/produits', '/boutique/stock', '/boutique/commandes', '/boutique/parametres'] as $path) {
            $this->assertSame(403, $this->get($kernel, $path)->status(), $path);
        }
    }

    public function testOwnerManagesProductsStockAndOrders(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $home = $this->get($kernel, '/espace');
        $this->assertSame(200, $home->status());
        $html = html_entity_decode($home->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Votre boutique en ligne', $html);
        $this->assertStringContainsString('/t/' . $this->slug($tenantId), $html);
        $this->assertStringContainsString('Ajouter un produit', $html);

        $created = $this->submit($kernel, '/boutique/produits', [
            'name' => 'Robe wax',
            'price_minor' => '25 000',
            'compare_price_minor' => '30000',
            'category' => 'robes',
            'stock_quantity' => '5',
            'low_stock_threshold' => '2',
            'track_stock' => '1',
            'is_published' => '1',
        ], '/boutique/produits/nouveau');
        $this->assertSame(302, $created->status());
        $productId = (int) $this->value('SELECT id FROM boutique_products WHERE tenant_id = ?', [$tenantId]);
        $this->assertSame(25000, (int) $this->value('SELECT price_minor FROM boutique_products WHERE id = ?', [$productId]));
        $this->assertSame('Robes', $this->value('SELECT category FROM boutique_products WHERE id = ?', [$productId]));
        $this->assertSame(5, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));

        $invalid = $this->submit($kernel, '/boutique/produits', ['name' => '', 'price_minor' => 'abc'], '/boutique/produits/nouveau');
        $this->assertSame(422, $invalid->status());

        $this->submit($kernel, '/boutique/stock', ['product_id' => (string) $productId, 'type' => 'in', 'quantity' => '3'], '/boutique/stock');
        $this->assertSame(8, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));
        $tooMuch = $this->submit($kernel, '/boutique/stock', ['product_id' => (string) $productId, 'type' => 'out', 'quantity' => '50'], '/boutique/stock');
        $this->assertSame(422, $tooMuch->status());

        $order = $this->submit($kernel, '/boutique/commandes', [
            'customer_name' => 'Awa Diop',
            'customer_phone' => '77 123 45 67',
            'product_id' => [(string) $productId],
            'quantity' => ['3'],
            'status' => 'new',
        ], '/boutique/commandes/nouveau');
        $this->assertSame(302, $order->status());
        $orderId = (int) $this->value('SELECT id FROM boutique_orders WHERE tenant_id = ?', [$tenantId]);
        $this->assertSame(75000, (int) $this->value('SELECT total_minor FROM boutique_orders WHERE id = ?', [$orderId]));
        $this->assertSame(8, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));

        $this->submit($kernel, '/boutique/commandes/' . $orderId . '/statut', ['status' => 'confirmed'], '/boutique/commandes/' . $orderId);
        $this->assertSame('confirmed', $this->value('SELECT status FROM boutique_orders WHERE id = ?', [$orderId]));
        $this->assertSame(5, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));

        $detail = html_entity_decode($this->get($kernel, '/boutique/commandes/' . $orderId)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('https://wa.me/221771234567', $detail);

        $this->submit($kernel, '/boutique/commandes/' . $orderId . '/statut', ['status' => 'cancelled'], '/boutique/commandes/' . $orderId);
        $this->assertSame(8, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));
        $this->assertSame(2, (int) $this->value('SELECT COUNT(*) FROM boutique_stock_movements WHERE order_id = ?', [$orderId]));

        $refused = $this->submit($kernel, '/boutique/commandes/' . $orderId . '/statut', ['status' => 'delivered'], '/boutique/commandes/' . $orderId);
        $this->assertSame(422, $refused->status());

        $this->submit($kernel, '/boutique/commandes/' . $orderId . '/paiement', [], '/boutique/commandes/' . $orderId);
        $this->assertSame(1, (int) $this->value('SELECT is_paid FROM boutique_orders WHERE id = ?', [$orderId]));

        $clientId = (int) $this->value('SELECT client_id FROM boutique_orders WHERE id = ?', [$orderId]);
        $client = html_entity_decode($this->get($kernel, '/clients/' . $clientId)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Commandes de la boutique', $client);

        $this->submit($kernel, '/boutique/produits/' . $productId . '/visibilite', [], '/boutique/produits');
        $this->assertSame(0, (int) $this->value('SELECT is_published FROM boutique_products WHERE id = ?', [$productId]));
        $this->assertStringNotContainsString('Robe wax', $this->get($kernel, '/t/' . $this->slug($tenantId))->content());
    }

    public function testSettingsUpdateTheShop(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $this->assertSame('/boutique/parametres', $this->get($kernel, '/parametres')->headers()['Location'] ?? '');

        $bad = $this->submit($kernel, '/boutique/parametres', ['display_name' => 'Ma boutique', 'whatsapp_phone' => '12'], '/boutique/parametres');
        $this->assertSame(422, $bad->status());

        $ok = $this->submit($kernel, '/boutique/parametres', [
            'display_name' => 'Chez Awa',
            'whatsapp_phone' => '77 123 45 67',
            'tagline' => 'Mode et accessoires',
            'theme' => 'corail',
            'instagram_url' => 'instagram.com/chezawa',
            'is_open' => '1',
        ], '/boutique/parametres');
        $this->assertSame(302, $ok->status());
        $this->assertSame('221771234567', $this->value('SELECT whatsapp_phone FROM boutique_settings WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame('https://instagram.com/chezawa', $this->value('SELECT instagram_url FROM boutique_settings WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame('Chez Awa', $this->value('SELECT display_name FROM tenant_settings WHERE tenant_id = ?', [$tenantId]));

        $public = html_entity_decode($this->get($kernel, '/t/' . $this->slug($tenantId))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Chez Awa', $public);
        $this->assertStringContainsString('Mode et accessoires', $public);
        $this->assertStringContainsString('#e8553e', $public);
    }

    public function testWhatsAppNumbersAreNormalized(): void
    {
        $this->assertSame('221771234567', WhatsApp::normalize('77 123 45 67'));
        $this->assertSame('221771234567', WhatsApp::normalize('+221 77 123 45 67'));
        $this->assertSame('33612345678', WhatsApp::normalize('0033 6 12 34 56 78'));
        $this->assertFalse(WhatsApp::isValid(WhatsApp::normalize('12')));
        $this->assertSame('https://wa.me/221771234567?text=Bonjour%20%21', WhatsApp::link('221771234567', 'Bonjour !'));
    }
}
