<?php
/**
 * Template de la page d'administration WooCommerce Fraud Shield
 *
 * @package WooCommerce_Fraud_Shield
 */

// Sécurité
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wfs-admin-page">
    <h1 class="wfs-title">
        🛡️ WooCommerce Fraud Shield
        <span class="wfs-version">v<?php echo esc_html(WFS_VERSION); ?></span>
    </h1>

    <div class="wfs-header-info">
        <div class="wfs-status-badge <?php echo $this->config['enabled'] ? 'enabled' : 'disabled'; ?>">
            <?php echo $this->config['enabled'] ? '🟢 ACTIF' : '🔴 INACTIF'; ?>
        </div>
        <div class="wfs-hpos-status">
            <strong>HPOS:</strong> <?php echo $this->config['hpos_enabled'] ? '✅ Compatible' : '⚠️ Legacy Mode'; ?>
        </div>
    </div>

    <div class="notice notice-info wfs-notice">
        <h3>🎯 Système Anti-Fraude Professionnel pour yoyaku.io</h3>
        <p><strong>Honeypot Product ID:</strong> <?php echo WFS_HONEYPOT_PRODUCT_ID; ?> | <strong>Frais de Sécurité:</strong> <?php echo WFS_SECURITY_FEE; ?>€</p>
        <p>Protection sophistiquée avec détection automatique et application immédiate des frais de sécurité.</p>
    </div>

    <div class="wfs-stats-grid">
        <div class="wfs-stat-card honeypot">
            <div class="wfs-stat-icon">🕷️</div>
            <div class="wfs-stat-content">
                <h3><?php echo number_format($this->stats['honeypot_detections']); ?></h3>
                <p>Détections Honeypot</p>
            </div>
        </div>

        <div class="wfs-stat-card security-fees">
            <div class="wfs-stat-icon">💰</div>
            <div class="wfs-stat-content">
                <h3><?php echo number_format($this->stats['security_fees_applied']); ?></h3>
                <p>Frais de Sécurité Appliqués</p>
            </div>
        </div>

        <div class="wfs-stat-card amount-protected">
            <div class="wfs-stat-icon">🔒</div>
            <div class="wfs-stat-content">
                <h3><?php echo number_format($this->stats['total_amount_protected'], 0); ?>€</h3>
                <p>Montant Total Protégé</p>
            </div>
        </div>

        <div class="wfs-stat-card alerts">
            <div class="wfs-stat-icon">🚨</div>
            <div class="wfs-stat-content">
                <h3><?php echo number_format($this->stats['alerts_sent']); ?></h3>
                <p>Alertes Envoyées</p>
            </div>
        </div>
    </div>

    <div class="wfs-admin-grid">
        <!-- Configuration -->
        <div class="wfs-admin-section wfs-settings">
            <h2>⚙️ Configuration</h2>

            <form method="post" class="wfs-settings-form">
                <?php wp_nonce_field('wfs_settings'); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="wfs_enabled"><?php _e('Protection Active', 'woocommerce-fraud-shield'); ?></label>
                            </th>
                            <td>
                                <label class="wfs-switch">
                                    <input type="checkbox" id="wfs_enabled" name="enabled" value="1" <?php checked($this->config['enabled']); ?>>
                                    <span class="wfs-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Master switch - Activer/désactiver toute la protection anti-fraude', 'woocommerce-fraud-shield'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wfs_auto_security_fee"><?php _e('Frais de Sécurité Auto', 'woocommerce-fraud-shield'); ?></label>
                            </th>
                            <td>
                                <label class="wfs-switch">
                                    <input type="checkbox" id="wfs_auto_security_fee" name="auto_security_fee" value="1" <?php checked($this->config['auto_security_fee']); ?>>
                                    <span class="wfs-slider"></span>
                                </label>
                                <p class="description">
                                    <?php echo sprintf(__('Application automatique des frais de %s€ lors de détection honeypot', 'woocommerce-fraud-shield'), WFS_SECURITY_FEE); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wfs_alert_threshold"><?php _e('Seuil d\'Alerte', 'woocommerce-fraud-shield'); ?></label>
                            </th>
                            <td>
                                <input type="range" id="wfs_alert_threshold" name="alert_threshold"
                                       min="1" max="100" value="<?php echo esc_attr($this->config['alert_threshold']); ?>"
                                       class="wfs-range-slider">
                                <span class="wfs-range-value"><?php echo esc_html($this->config['alert_threshold']); ?>%</span>
                                <p class="description">
                                    <?php _e('Score de risque minimum pour déclencher une alerte (recommandé: 60)', 'woocommerce-fraud-shield'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wfs_email_alerts"><?php _e('Alertes Email', 'woocommerce-fraud-shield'); ?></label>
                            </th>
                            <td>
                                <label class="wfs-switch">
                                    <input type="checkbox" id="wfs_email_alerts" name="email_alerts" value="1" <?php checked($this->config['email_alerts']); ?>>
                                    <span class="wfs-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Envoyer des notifications email lors d\'alertes de sécurité', 'woocommerce-fraud-shield'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="wfs_log_all_attempts"><?php _e('Logs Complets', 'woocommerce-fraud-shield'); ?></label>
                            </th>
                            <td>
                                <label class="wfs-switch">
                                    <input type="checkbox" id="wfs_log_all_attempts" name="log_all_attempts" value="1" <?php checked($this->config['log_all_attempts']); ?>>
                                    <span class="wfs-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enregistrer toutes les tentatives et analyses pour audit complet', 'woocommerce-fraud-shield'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="wfs-save-section">
                    <?php submit_button(__('Sauvegarder les Paramètres', 'woocommerce-fraud-shield'), 'primary large', 'submit', true, ['id' => 'wfs-save-settings']); ?>
                </div>
            </form>
        </div>

        <!-- Statistiques en Temps Réel -->
        <div class="wfs-admin-section wfs-live-stats">
            <h2>📊 Statistiques du Mois</h2>
            <div id="wfs-monthly-stats">
                <div class="wfs-loading">Chargement...</div>
            </div>
            <button type="button" class="button" id="wfs-refresh-stats">🔄 Actualiser</button>
        </div>
    </div>

    <!-- Section Logs et Monitoring -->
    <div class="wfs-admin-section wfs-logs-section">
        <h2>📋 Monitoring et Logs</h2>

        <div class="wfs-logs-controls">
            <button type="button" class="button" id="wfs-view-honeypot-logs">🕷️ Logs Honeypot</button>
            <button type="button" class="button" id="wfs-view-alert-logs">🚨 Logs Alertes</button>
            <button type="button" class="button" id="wfs-view-analysis-logs">📈 Logs Analyses</button>
            <button type="button" class="button button-secondary" id="wfs-clear-logs">🗑️ Nettoyer les Logs</button>
        </div>

        <div id="wfs-logs-display" class="wfs-logs-display">
            <div class="wfs-logs-empty">
                Sélectionnez un type de log pour afficher les données.
            </div>
        </div>
    </div>

    <!-- Section Informations Système -->
    <div class="wfs-admin-section wfs-system-info">
        <h2>🔧 Informations Système</h2>

        <div class="wfs-system-grid">
            <div class="wfs-system-item">
                <strong>Version Plugin:</strong>
                <span class="wfs-version-badge"><?php echo WFS_VERSION; ?></span>
            </div>

            <div class="wfs-system-item">
                <strong>WordPress:</strong>
                <span><?php echo get_bloginfo('version'); ?></span>
            </div>

            <div class="wfs-system-item">
                <strong>WooCommerce:</strong>
                <span><?php echo defined('WC_VERSION') ? WC_VERSION : 'Non détecté'; ?></span>
            </div>

            <div class="wfs-system-item">
                <strong>PHP:</strong>
                <span><?php echo PHP_VERSION; ?></span>
            </div>

            <div class="wfs-system-item">
                <strong>Mode HPOS:</strong>
                <span class="<?php echo $this->config['hpos_enabled'] ? 'wfs-enabled' : 'wfs-disabled'; ?>">
                    <?php echo $this->config['hpos_enabled'] ? 'Activé' : 'Legacy'; ?>
                </span>
            </div>

            <div class="wfs-system-item">
                <strong>Produit Honeypot:</strong>
                <span class="wfs-honeypot-id"><?php echo WFS_HONEYPOT_PRODUCT_ID; ?></span>
            </div>

            <div class="wfs-system-item">
                <strong>Frais de Sécurité:</strong>
                <span class="wfs-security-fee"><?php echo WFS_SECURITY_FEE; ?>€</span>
            </div>

            <div class="wfs-system-item">
                <strong>Dossier Logs:</strong>
                <span class="wfs-log-path"><?php echo WFS_LOG_DIR; ?></span>
            </div>
        </div>
    </div>

    <!-- Section Documentation Rapide -->
    <div class="wfs-admin-section wfs-documentation">
        <h2>📚 Guide Rapide</h2>

        <div class="wfs-doc-grid">
            <div class="wfs-doc-item">
                <h3>🕷️ Honeypot Detection</h3>
                <p>Le produit ID <strong><?php echo WFS_HONEYPOT_PRODUCT_ID; ?></strong> est un piège automatique. Toute tentative d'ajout au panier déclenche l'application immédiate de frais de sécurité de <strong><?php echo WFS_SECURITY_FEE; ?>€</strong>.</p>
            </div>

            <div class="wfs-doc-item">
                <h3>🔍 Analyse de Risque</h3>
                <p>Chaque commande est analysée avec un score de risque basé sur l'email, la géolocalisation, le montant, les patterns comportementaux et techniques.</p>
            </div>

            <div class="wfs-doc-item">
                <h3>🚨 Système d'Alertes</h3>
                <p>Les alertes sont envoyées automatiquement par email lorsque le score de risque dépasse le seuil configuré ou lors de détections honeypot.</p>
            </div>

            <div class="wfs-doc-item">
                <h3>📊 Compatibilité HPOS</h3>
                <p>Plugin entièrement compatible avec le High-Performance Order Storage de WooCommerce avec déclaration officielle.</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="wfs-admin-footer">
        <p>
            <strong>WooCommerce Fraud Shield</strong> v<?php echo WFS_VERSION; ?> -
            Protection professionnelle pour <strong>yoyaku.io</strong> |
            <a href="https://yoyaku.io" target="_blank">yoyaku.io</a>
        </p>
    </div>
</div>

<script type="text/javascript">
// Variables globales pour l'admin
window.wfsConfig = {
    honeypotProductId: <?php echo WFS_HONEYPOT_PRODUCT_ID; ?>,
    securityFee: <?php echo WFS_SECURITY_FEE; ?>,
    enabled: <?php echo $this->config['enabled'] ? 'true' : 'false'; ?>,
    hposEnabled: <?php echo $this->config['hpos_enabled'] ? 'true' : 'false'; ?>
};
</script>