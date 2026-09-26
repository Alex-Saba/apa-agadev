<?php
defined('ABSPATH') || exit;
wp_enqueue_script('plugin-apa-agadev');
if (isset($savedOrder)) : ?>
<section class="acl_shortcode_apa_form acl_shortcode_apa_form--success" data-apa-order-success>
    <div class="acl_shortcode_notice acl_shortcode_notice--success" role="status">
        <?php echo ($savedOrder['status'] ?? '') === 'draft'
            ? 'Votre brouillon a bien été enregistré. Vous pourrez le reprendre depuis la liste des commandes.'
            : 'Votre commande a bien été soumise. Vous pouvez suivre son statut depuis la liste des commandes.'; ?>
    </div>
</section>
<?php return; endif;
$selectedUnit = '';
?>
<section class="apa-orders">
    <h2 class="apa-order-content-title"><?php echo $uuid !== '' ? 'Modifier la commande' : 'Nouvelle commande'; ?></h2>
    <?php echo $error; // Already escaped by the shortcode error renderer. ?>
    <form method="post" action="<?php echo esc_url($this->url(['order_action' => $uuid === '' ? 'new' : 'edit', 'order_uuid' => $uuid])); ?>" class="apa-orders-form acl_shortcode_apa_form">
        <?php wp_nonce_field('apa_order_save', '_apa_order_nonce'); ?>
        <p class="apa-order-help">Choisissez votre produit, puis précisez les caractéristiques souhaitées. Les champs marqués * sont obligatoires.</p>
        <fieldset class="apa-order-group"><legend>Produit et variantes</legend>
        <input type="hidden" name="order_uuid" value="<?php echo esc_attr($uuid); ?>">
        <div class="acl_shortcode_field acl_shortcode_apa_field acl_shortcode_apa_field--full"><label class="acl_shortcode_label" for="apa-order-product">Produit actif <span class="acl_shortcode_required" aria-hidden="true">*</span></label>
            <select class="acl_shortcode_select" id="apa-order-product" name="order[product_uuid]" data-apa-order-product required>
                <option value="">Sélectionner un produit</option>
                <?php $selectedProduct = (string) ($values['product_uuid'] ?? ''); $known = false; ?>
                <?php foreach ($products as $product) : ?>
                    <?php $selected = $selectedProduct === $product['uuid']; $known = $known || $selected; $available = is_string($product['assigned_packaging_unit'] ?? null) && trim($product['assigned_packaging_unit']) !== ''; ?>
                    <?php $productUnit = $available ? trim($product['assigned_packaging_unit']) : ''; if ($selected) { $selectedUnit = $productUnit; } ?>
                    <option data-apa-order-unit="<?php echo esc_attr($productUnit); ?>" value="<?php echo esc_attr($product['uuid']); ?>" <?php echo $selected ? 'selected' : ''; ?> <?php echo ! $available ? 'disabled' : ''; ?>><?php echo esc_html((string) ($product['name'] ?? $product['uuid']) . (! $available ? ' — unité non renseignée' : '')); ?></option>
                <?php endforeach; ?>
                <?php if ($selectedProduct !== '' && ! $known) : ?><option selected disabled value="<?php echo esc_attr($selectedProduct); ?>">Produit indisponible — sélectionnez un autre produit</option><?php endif; ?>
            </select>
        </div>
        <?php foreach ($products as $variantProduct) : ?>
            <?php $variantActive = $selectedProduct === $variantProduct['uuid']; ?>
            <?php foreach ((array) ($variantProduct['meta'] ?? []) as $variantKey => $definition) : ?>
                <?php if (! is_array($definition) || ! (array_values($definition) === $definition)) { continue; } ?>
                <?php $choices = array_map('strval', array_filter($definition, 'is_scalar')); $chosen = $variantActive && is_string($values['meta'][$variantKey] ?? null) ? $values['meta'][$variantKey] : ''; ?>
                <div class="acl_shortcode_field acl_shortcode_apa_field" data-apa-order-variant="<?php echo esc_attr($variantProduct['uuid']); ?>"<?php echo $variantActive ? '' : ' hidden'; ?>>
                    <label class="acl_shortcode_label"><?php echo esc_html(['formes' => 'Formes', 'couleurs' => 'Couleurs'][strtolower((string) $variantKey)] ?? (string) $variantKey); ?>
                        <select class="acl_shortcode_select" name="order[meta][<?php echo esc_attr((string) $variantKey); ?>]" required<?php echo $variantActive ? '' : ' disabled'; ?>>
                            <option value="">Sélectionner</option>
                            <?php foreach ($choices as $choice) : ?><option value="<?php echo esc_attr($choice); ?>"<?php echo $chosen === $choice ? ' selected' : ''; ?>><?php echo esc_html($choice); ?></option><?php endforeach; ?>
                            <?php if ($chosen !== '' && ! in_array($chosen, $choices, true)) : ?><option selected disabled value="">Choix indisponible — sélectionnez une valeur</option><?php endif; ?>
                        </select>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($products === []) : ?><p role="status">Aucun produit actif disponible.</p><?php endif; ?>
        </fieldset>
        <fieldset class="apa-order-group"><legend>Quantité et livraison</legend>
        <div class="acl_shortcode_field acl_shortcode_apa_field acl_shortcode_apa_field--full"><label class="acl_shortcode_label" for="apa-order-quantity">Quantité <span data-apa-order-unit-label aria-live="polite"><?php echo esc_html($selectedProduct === '' ? '' : ($selectedUnit !== '' ? '(' . $selectedUnit . ')' : '(unité non renseignée)')); ?></span> <span class="acl_shortcode_required" aria-hidden="true">*</span></label>
            <input class="acl_shortcode_input_text" id="apa-order-quantity" name="order[quantity]" type="text" inputmode="decimal" maxlength="32" pattern="[0-9]+([.,][0-9]{1,3})?" placeholder="10.000" required value="<?php echo esc_attr((string) ($values['quantity'] ?? '')); ?>" aria-describedby="apa-order-quantity-help">
        <p class="apa-order-help" id="apa-order-quantity-help">Maximum trois décimales.</p></div>
        <div class="acl_shortcode_field acl_shortcode_apa_field acl_shortcode_apa_field--full"><label class="acl_shortcode_label" for="apa-order-date">Date de livraison attendue</label>
            <input class="acl_shortcode_input_text" id="apa-order-date" aria-describedby="apa-order-date-help" name="order[expected_delivery_date]" type="date" value="<?php echo esc_attr((string) ($values['expected_delivery_date'] ?? '')); ?>">
        <p class="apa-order-help" id="apa-order-date-help">Facultative, modifiable tant que la commande reste en brouillon.</p></div>
        </fieldset>
        <p class="apa-order-help">Le brouillon reste modifiable. Après soumission, la commande sera consultable en lecture seule.</p>
        <p data-apa-order-feedback role="status" aria-live="polite"></p>
        <div class="acl_shortcode_actions acl_shortcode_apa_step_actions">
            <a class="acl_shortcode_btn acl_shortcode_button_button apa-order-cancel" href="<?php echo esc_url($this->url()); ?>">Annuler</a>
            <button type="submit" name="apa_order_intent" value="draft" class="acl_shortcode_btn acl_shortcode_button_button acl_shortcode_apa_save_draft">Enregistrer le brouillon</button>
            <?php if ($canSubmit) : ?><button type="submit" name="apa_order_intent" value="submit" class="acl_shortcode_submit acl_shortcode_button_submit">Soumettre la commande</button><?php endif; ?>
        </div>
    </form>
</section>
