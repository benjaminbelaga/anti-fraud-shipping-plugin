/**
 * WooCommerce Fraud Shield - Admin JavaScript
 *
 * Interface interactive pour l'administration du plugin
 * Compatible jQuery et WordPress
 */

(function($) {
    'use strict';

    /**
     * Classe principale pour l'administration WFS
     */
    class WFSAdmin {
        constructor() {
            this.init();
        }

        /**
         * Initialisation
         */
        init() {
            this.bindEvents();
            this.initRangeSlider();
            this.loadMonthlyStats();
            this.setupAutoRefresh();
        }

        /**
         * Liaison des événements
         */
        bindEvents() {
            // Refresh des statistiques
            $('#wfs-refresh-stats').on('click', () => this.loadMonthlyStats());

            // Gestion des logs
            $('#wfs-view-honeypot-logs').on('click', () => this.viewLogs('honeypot'));
            $('#wfs-view-alert-logs').on('click', () => this.viewLogs('high_risk_alert'));
            $('#wfs-view-analysis-logs').on('click', () => this.viewLogs('order_analysis'));
            $('#wfs-clear-logs').on('click', () => this.clearLogs());

            // Range slider en temps réel
            $('#wfs_alert_threshold').on('input', this.updateRangeValue);

            // Sauvegarde avec feedback
            $('#wfs-save-settings').on('click', this.handleSaveSettings);

            // Switches avec animation
            $('.wfs-switch input').on('change', this.handleSwitchChange);
        }

        /**
         * Initialiser le range slider
         */
        initRangeSlider() {
            const $slider = $('#wfs_alert_threshold');
            const $value = $('.wfs-range-value');

            $slider.on('input', function() {
                const value = $(this).val();
                $value.text(value + '%');

                // Couleur dynamique basée sur la valeur
                let color = '#00a32a'; // Vert pour bas risque
                if (value >= 60) color = '#dba617'; // Orange pour moyen risque
                if (value >= 80) color = '#d63638'; // Rouge pour haut risque

                $value.css('background-color', color);
            });

            // Trigger initial
            $slider.trigger('input');
        }

        /**
         * Charger les statistiques mensuelles
         */
        loadMonthlyStats() {
            const $container = $('#wfs-monthly-stats');
            const $button = $('#wfs-refresh-stats');

            $container.html('<div class="wfs-loading">📊 Chargement des statistiques...</div>');
            $button.prop('disabled', true).text('⏳ Chargement...');

            $.ajax({
                url: wfs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wfs_get_live_stats',
                    nonce: wfs_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayMonthlyStats(response.data);
                    } else {
                        $container.html('<div class="notice notice-error"><p>Erreur lors du chargement des statistiques.</p></div>');
                    }
                },
                error: () => {
                    $container.html('<div class="notice notice-error"><p>Erreur de connexion.</p></div>');
                },
                complete: () => {
                    $button.prop('disabled', false).text('🔄 Actualiser');
                }
            });
        }

        /**
         * Afficher les statistiques mensuelles
         */
        displayMonthlyStats(data) {
            const { monthly, stats, timestamp } = data;

            const html = `
                <div class="wfs-monthly-display">
                    <h3>📅 Statistiques de ${this.getCurrentMonth()}</h3>

                    <div class="wfs-monthly-grid">
                        <div class="wfs-monthly-item">
                            <span class="wfs-monthly-icon">📊</span>
                            <div>
                                <strong>${monthly.total_events.toLocaleString()}</strong>
                                <small>Événements Total</small>
                            </div>
                        </div>

                        <div class="wfs-monthly-item">
                            <span class="wfs-monthly-icon">🕷️</span>
                            <div>
                                <strong>${monthly.honeypot_detections.toLocaleString()}</strong>
                                <small>Détections Honeypot</small>
                            </div>
                        </div>

                        <div class="wfs-monthly-item">
                            <span class="wfs-monthly-icon">🚨</span>
                            <div>
                                <strong>${monthly.high_risk_alerts.toLocaleString()}</strong>
                                <small>Alertes Haut Risque</small>
                            </div>
                        </div>

                        <div class="wfs-monthly-item">
                            <span class="wfs-monthly-icon">📈</span>
                            <div>
                                <strong>${monthly.order_analyses.toLocaleString()}</strong>
                                <small>Analyses Commandes</small>
                            </div>
                        </div>
                    </div>

                    <div class="wfs-efficiency-meter">
                        <h4>🎯 Efficacité de Protection</h4>
                        <div class="wfs-efficiency-bar">
                            <div class="wfs-efficiency-fill" style="width: ${this.calculateEfficiency(monthly)}%"></div>
                        </div>
                        <small>Dernière mise à jour: ${this.formatTimestamp(timestamp)}</small>
                    </div>
                </div>
            `;

            $('#wfs-monthly-stats').html(html);
        }

        /**
         * Calculer l'efficacité de protection
         */
        calculateEfficiency(stats) {
            if (stats.total_events === 0) return 0;

            const detectionRate = (stats.honeypot_detections / stats.total_events) * 100;
            const alertRate = (stats.high_risk_alerts / stats.total_events) * 100;

            // Formule personnalisée pour l'efficacité
            return Math.min(100, Math.round((detectionRate * 2) + (alertRate * 1.5)));
        }

        /**
         * Obtenir le mois actuel formaté
         */
        getCurrentMonth() {
            const date = new Date();
            const months = [
                'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
            ];
            return `${months[date.getMonth()]} ${date.getFullYear()}`;
        }

        /**
         * Formater un timestamp
         */
        formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        /**
         * Afficher les logs
         */
        viewLogs(logType) {
            const $display = $('#wfs-logs-display');
            const $buttons = $('.wfs-logs-controls .button');

            // Feedback visuel
            $buttons.removeClass('button-primary');
            $(`#wfs-view-${logType.replace('_', '-')}-logs`).addClass('button-primary');

            $display.html('<div class="wfs-loading">📋 Chargement des logs...</div>');

            // Simuler le chargement des logs (à remplacer par un vrai AJAX)
            setTimeout(() => {
                const logsContent = this.generateSampleLogs(logType);
                $display.html(`
                    <div class="wfs-logs-header">
                        <h4>📋 Logs: ${this.getLogTypeLabel(logType)}</h4>
                        <span class="wfs-logs-count">${logsContent.split('\n').length} entrées</span>
                    </div>
                    <pre class="wfs-logs-content">${logsContent}</pre>
                `);
            }, 500);
        }

        /**
         * Obtenir le label du type de log
         */
        getLogTypeLabel(logType) {
            const labels = {
                'honeypot': '🕷️ Détections Honeypot',
                'high_risk_alert': '🚨 Alertes Haut Risque',
                'order_analysis': '📈 Analyses de Commandes'
            };
            return labels[logType] || logType;
        }

        /**
         * Générer des exemples de logs
         */
        generateSampleLogs(logType) {
            const now = new Date();
            const samples = [];

            for (let i = 0; i < 10; i++) {
                const timestamp = new Date(now.getTime() - (i * 3600000)).toISOString();

                switch (logType) {
                    case 'honeypot':
                        samples.push(`[${timestamp}] HONEYPOT DETECTION - Product: ${window.wfsConfig.honeypotProductId} | IP: 192.168.1.${100 + i} | Fee Applied: ${window.wfsConfig.securityFee}€`);
                        break;
                    case 'high_risk_alert':
                        samples.push(`[${timestamp}] HIGH RISK ALERT - Score: ${60 + Math.floor(Math.random() * 40)}/100 | Order: #${12000 + i} | IP: 10.0.0.${50 + i}`);
                        break;
                    case 'order_analysis':
                        samples.push(`[${timestamp}] ORDER ANALYSIS - Order: #${12000 + i} | Score: ${Math.floor(Math.random() * 100)}/100 | Mode: ${window.wfsConfig.hposEnabled ? 'HPOS' : 'Legacy'}`);
                        break;
                }
            }

            return samples.join('\n');
        }

        /**
         * Nettoyer les logs
         */
        clearLogs() {
            if (!confirm('⚠️ Êtes-vous sûr de vouloir nettoyer tous les logs ? Cette action est irréversible.')) {
                return;
            }

            const $display = $('#wfs-logs-display');

            $.ajax({
                url: wfs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wfs_clear_logs',
                    log_type: 'all',
                    nonce: wfs_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $display.html('<div class="wfs-logs-empty">✅ Logs nettoyés avec succès.</div>');
                        this.showNotice('Logs nettoyés avec succès.', 'success');

                        // Refresh des statistiques
                        this.loadMonthlyStats();
                    } else {
                        this.showNotice('Erreur lors du nettoyage des logs.', 'error');
                    }
                },
                error: () => {
                    this.showNotice('Erreur de connexion lors du nettoyage.', 'error');
                }
            });
        }

        /**
         * Mettre à jour la valeur du range
         */
        updateRangeValue() {
            const value = $(this).val();
            $('.wfs-range-value').text(value + '%');
        }

        /**
         * Gérer la sauvegarde des paramètres
         */
        handleSaveSettings(e) {
            const $button = $(this);
            const originalText = $button.text();

            $button.prop('disabled', true).text('💾 Sauvegarde...');

            // Animation du bouton
            setTimeout(() => {
                $button.text('✅ Sauvegardé !').css('background-color', '#00a32a');

                setTimeout(() => {
                    $button.prop('disabled', false).text(originalText).css('background-color', '');
                }, 1500);
            }, 800);
        }

        /**
         * Gérer les changements de switch
         */
        handleSwitchChange() {
            const $switch = $(this);
            const isChecked = $switch.is(':checked');
            const label = $switch.closest('tr').find('th label').text();

            // Animation de feedback
            const $slider = $switch.next('.wfs-slider');
            $slider.addClass('wfs-switch-animation');

            setTimeout(() => {
                $slider.removeClass('wfs-switch-animation');
            }, 300);

            // Log de l'action (optionnel)
            console.log(`WFS: ${label} ${isChecked ? 'activé' : 'désactivé'}`);
        }

        /**
         * Configurer le refresh automatique
         */
        setupAutoRefresh() {
            // Refresh automatique des stats toutes les 5 minutes
            setInterval(() => {
                this.loadMonthlyStats();
            }, 300000);
        }

        /**
         * Afficher une notice
         */
        showNotice(message, type = 'info') {
            const noticeClass = `notice notice-${type}`;
            const notice = $(`
                <div class="${noticeClass} is-dismissible wfs-dynamic-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Insérer après le titre
            $('.wfs-title').after(notice);

            // Auto-dismiss après 5 secondes
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);

            // Dismiss manuel
            notice.find('.notice-dismiss').on('click', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
    }

    /**
     * Utilitaires CSS dynamiques
     */
    class WFSStyles {
        static addDynamicStyles() {
            const style = $(`
                <style id="wfs-dynamic-styles">
                    .wfs-monthly-display {
                        background: linear-gradient(135deg, #f6f7f7 0%, #e9ecef 100%);
                        padding: 20px;
                        border-radius: 8px;
                        border: 1px solid #dcdcde;
                    }

                    .wfs-monthly-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                        gap: 15px;
                        margin: 20px 0;
                    }

                    .wfs-monthly-item {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        background: white;
                        padding: 15px;
                        border-radius: 6px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    }

                    .wfs-monthly-icon {
                        font-size: 1.5em;
                        opacity: 0.8;
                    }

                    .wfs-monthly-item strong {
                        display: block;
                        font-size: 1.2em;
                        color: #1d2327;
                    }

                    .wfs-monthly-item small {
                        color: #666;
                        font-size: 0.85em;
                    }

                    .wfs-efficiency-meter {
                        margin-top: 20px;
                        padding-top: 15px;
                        border-top: 1px solid #dcdcde;
                    }

                    .wfs-efficiency-bar {
                        width: 100%;
                        height: 20px;
                        background: #e9ecef;
                        border-radius: 10px;
                        overflow: hidden;
                        margin: 10px 0;
                    }

                    .wfs-efficiency-fill {
                        height: 100%;
                        background: linear-gradient(90deg, #00a32a 0%, #2271b1 100%);
                        transition: width 1s ease-in-out;
                        border-radius: 10px;
                    }

                    .wfs-logs-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 15px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid #444;
                    }

                    .wfs-logs-count {
                        background: #2271b1;
                        color: white;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 0.85em;
                    }

                    .wfs-logs-content {
                        margin: 0;
                        white-space: pre-wrap;
                        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
                        font-size: 12px;
                        line-height: 1.4;
                    }

                    .wfs-switch-animation {
                        animation: wfs-switch-glow 0.3s ease-in-out;
                    }

                    @keyframes wfs-switch-glow {
                        0% { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0.7); }
                        50% { box-shadow: 0 0 0 8px rgba(34, 113, 177, 0.3); }
                        100% { box-shadow: 0 0 0 0 rgba(34, 113, 177, 0); }
                    }

                    .wfs-dynamic-notice {
                        animation: wfs-slide-down 0.3s ease-out;
                    }

                    @keyframes wfs-slide-down {
                        from {
                            transform: translateY(-20px);
                            opacity: 0;
                        }
                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                </style>
            `);

            $('head').append(style);
        }
    }

    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        // Vérifier que nous sommes sur la page WFS
        if (!$('.wfs-admin-page').length) {
            return;
        }

        // Ajouter les styles dynamiques
        WFSStyles.addDynamicStyles();

        // Initialiser l'admin
        new WFSAdmin();

        // Animation d'entrée pour les cartes
        $('.wfs-stat-card, .wfs-admin-section').each(function(index) {
            $(this).css({
                'opacity': '0',
                'transform': 'translateY(20px)'
            }).delay(index * 100).animate({
                'opacity': '1'
            }, 500).css('transform', 'translateY(0)');
        });

        // Confirmation avant navigation si des changements non sauvés
        let hasUnsavedChanges = false;

        $('.wfs-settings-form input, .wfs-settings-form select').on('change', function() {
            hasUnsavedChanges = true;
        });

        $('.wfs-settings-form').on('submit', function() {
            hasUnsavedChanges = false;
        });

        $(window).on('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                const message = 'Vous avez des modifications non sauvegardées. Voulez-vous vraiment quitter ?';
                e.returnValue = message;
                return message;
            }
        });

        // Log de l'initialisation
        console.log('🛡️ WooCommerce Fraud Shield Admin initialized');
        console.log('Config:', window.wfsConfig);
    });

})(jQuery);