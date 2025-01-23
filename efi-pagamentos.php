<?php
 $autoload = realpath(__DIR__ . '/vendor/autoload.php');
 if (!file_exists($autoload)) {
     die("Autoload file not found or on path <code>$autoload</code>.");
 }
 require_once $autoload;
 use Efi\Exception\EfiException;
use Efi\EfiPay;
/*
Plugin Name: EFI Pagamentos
Description: Plugin para configurar os campos de pagamento EFI.
Version: 1.0
Author: Thieko Kumagai
*/

// Evitar acesso direto ao arquivo
defined('ABSPATH') || exit;

// Criar menu no painel administrativo
function efi_pagamentos_create_menu() {
    add_menu_page(
        'EFI Pagamentos',
        'EFI Pagamentos',
        'manage_options',
        'efi-pagamentos',
        'efi_pagamentos_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'efi_pagamentos_create_menu');

// Permitir upload de arquivos .p12
function efi_pagamentos_allow_p12_upload($mime_types) {
    $mime_types['p12'] = 'application/x-pkcs12';
    return $mime_types;
}
add_filter('upload_mimes', 'efi_pagamentos_allow_p12_upload');

// Página de configurações do plugin
function efi_pagamentos_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações EFI Pagamentos</h1>
        <button id="create-webhook-btn" style="margin-top:10px;" class="button">Criar Webhook</button>
        <div id="webhook-status" style="margin-top:10px;"></div>

        <script>
            document.getElementById('create-webhook-btn').addEventListener('click', function() {
                const statusDiv = document.getElementById('webhook-status');
                statusDiv.innerHTML = 'Criando o Webhook...';

                fetch('<?php echo esc_url(rest_url('efi/v1/criar-webhook')); ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            statusDiv.innerHTML = 'Webhook criado com sucesso!';
                            // Salvar a URL do Webhook nas opções
                            const webhookUrl = data.data.response.webhookUrl;
                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    action: 'save_webhook_url',
                                    webhook_url: webhookUrl
                                })
                            })
                            .then(response => response.json())
                            .then(saveData => {
                                console.log(saveData);
                                if (saveData.success) {
                                    alert('Webhook URL salva nas configurações');
                                    location.reload();
                                }
                            });
                        } else {
                            statusDiv.innerHTML = 'Erro ao criar Webhook: ' + data.message;
                        }
                    })
                    .catch(error => {
                        statusDiv.innerHTML = 'Erro: ' + error.message;
                    });
            });
        </script>
        <form method="post" action="options.php" style="margin-top:20px;" enctype="multipart/form-data">
            <?php
            settings_fields('efi_pagamentos_settings');
            do_settings_sections('efi-pagamentos');
            submit_button();
            ?>
        </form>
        
    </div>
    <?php
}

// Registrar configurações
function efi_pagamentos_settings_init() {
    register_setting('efi_pagamentos_settings', 'efi_pagamentos_options');

    add_settings_section(
        'efi_post_section',
        'Vínculo com post',
        null,
        'efi-pagamentos'
    );

    $post_fields = [             
        'tipo_post' => 'Tipo de Post',
        'data_criacao' => 'Data Criação',
        'forma_pagamento'=> 'Forma de Pagamento',
        'valor'=>'Valor',
        'data_pagamento'=>'Data de Pagamento',
        'status'=>'Status Efí',  
        'pago'=>'Status Controle',       
        'charge_id'=>'Identificador (Cartão)',
        'date_of_expiration'=>'Data de Expiração (Pix)',
        'qr_code_base64'=> 'QR Code Base64 (Pix)',
        'qr_code'=>'QR Code (Pix)',
        'txid'=>'Identificador (Pix)'
    ];

    foreach ($post_fields as $field => $label) {
        add_settings_field(
            $field,
            $label,
            'efi_pagamentos_field_callback',
            'efi-pagamentos',
            'efi_post_section',
            ['label_for' => $field]
        );
    }
    // Seção principal
    add_settings_section(
        'efi_pagamentos_section',
        'Credenciais',
        null,
        'efi-pagamentos'
    );

    $fields = [
             
        'client_id' => 'Client ID Produção',
        'client_secret' => 'Client Secret Produção',
        'client_id_homologacao' => 'Client ID Homologação',
        'client_secret_homologacao' => 'Client Secret Homologação',
        'account' => 'Código Identificador de conta',
        'timeout' => 'Timeout',
        'environment' => 'Ambiente',
        'webhook'=>'Webhook',
        'patch_certificate_homologacao_save' => '',
    ];

    foreach ($fields as $field => $label) {
        add_settings_field(
            $field,
            $label,
            'efi_pagamentos_field_callback',
            'efi-pagamentos',
            'efi_pagamentos_section',
            ['label_for' => $field]
        );
    }

    // Seção de Configurações do Pix
    add_settings_section(
        'efi_pagamentos_pix_section',
        'Configurações do Pix',
        null,
        'efi-pagamentos'
    );

    $pix_fields = [
        'pix' => 'Chave Pix',
        'patch_certificate' => 'Certificado Pix Produção',
        'patch_certificate_homologacao' => 'Certificado Pix Homologação',
        'patch_certificate_save' => ''
        
    ];

    foreach ($pix_fields as $field => $label) {
        add_settings_field(
            $field,
            $label,
            'efi_pagamentos_field_callback',
            'efi-pagamentos',
            'efi_pagamentos_pix_section',
            ['label_for' => $field]
        );
    }
}
add_action('admin_init', 'efi_pagamentos_settings_init');

// Callback para renderizar os campos 
function efi_pagamentos_field_callback($args) {
    $options = get_option('efi_pagamentos_options', []);
    $field = $args['label_for'];
    if ($field === 'webhook') {
        $value = isset($options[$field]) ? $options[$field] : '';
        echo "<p>$value</p>";
        echo "<input type='hidden' id='$field' name='efi_pagamentos_options[$field]' value='$value' class='regular-text'  readonly/>";
    }else if (in_array($field, ['patch_certificate_save', 'patch_certificate_homologacao_save'])) {
        $value = isset($options[$field]) ? $options[$field] : '';
        echo "<input type='hidden' id='$field' name='efi_pagamentos_options[$field]' value='$value' class='regular-text' />";
    }else if (in_array($field, ['patch_certificate', 'patch_certificate_homologacao'])) {
        // Campo para upload de arquivo
        $value = isset($options[$field]) ? $options[$field] : '';
        if (!empty($value)) {
            echo "<p>Arquivo atual: <a href='$value' target='_blank'>" . basename($value) . "</a></p>";
            echo "<button type='button' class='button delete-file' data-field='$field'>Excluir arquivo</button>";
            echo "<input type='hidden' id='$field' name='$field' />";
        } else {
            echo "<input type='file' id='$field' name='$field' value='$value' />";
        }

        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const deleteButtons = document.querySelectorAll('.delete-file');
                
                deleteButtons.forEach(button => {
                    if (!button.dataset.listenerAdded) {
                        button.addEventListener('click', function () {
                            if (confirm('Tem certeza que deseja excluir este arquivo?')) {
                                const field = this.dataset.field;
                                fetch(ajaxurl, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams({
                                        action: 'delete_file',
                                        field: field
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert('Arquivo excluído com sucesso!');
                                        location.reload();
                                    } else {
                                        alert('Erro ao excluir o arquivo: ' + data.message);
                                    }
                                })
                                .catch(error => {
                                    console.error('Erro na requisição:', error);
                                    alert('Erro na requisição. Verifique o console para mais detalhes.');
                                });
                            }
                        });
                        button.dataset.listenerAdded = true;
                    }
                });
            });
        </script>
        ";
    } elseif ($field === 'environment') {
        $value = isset($options[$field]) ? $options[$field] : 'producao';
        echo "<select id='$field' name='efi_pagamentos_options[$field]'>                
                <option value='homologacao' " . selected($value, 'homologacao', false) . ">Homologação</option>
                <option value='producao' " . selected($value, 'producao', false) . ">Produção</option>
              </select>";
    } elseif ($field === 'timeout') {
        $value = isset($options[$field]) ? $options[$field] : 30;
        echo "<input type='number' id='$field' name='efi_pagamentos_options[$field]' value='$value' class='regular-text' min='1' />";
        echo "<p class='description'>Tempo limite em segundos (padrão: 30).</p>";
    } elseif ($field === 'tipo_post') {
        $value = isset($options[$field]) ? $options[$field] : '';
        $post_types = get_post_types(['public' => true], 'objects'); // Apenas post types públicos
        echo "<select id='tipo_post' name='efi_pagamentos_options[$field]'>";
        foreach ($post_types as $post_type => $post_type_obj) {
            echo "<option value='$post_type' " . selected($value, $post_type, false) . ">{$post_type_obj->labels->name}</option>";
        }
        echo "</select>";
    } else {
        $value = isset($options[$field]) ? $options[$field] : '';
        echo "<input type='text' id='$field' name='efi_pagamentos_options[$field]' value='$value' class='regular-text' />";
    }
}
function efi_pagamentos_admin_notices() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        add_settings_error(
            'efi_pagamentos_messages', 
            'efi_pagamentos_success', 
            'Configurações salvas com sucesso!', 
            'updated' 
        );
    }
    settings_errors('efi_pagamentos_messages');
}
add_action('admin_notices', 'efi_pagamentos_admin_notices');
// Gerencia o upload do certificado de produção
function efi_upload_patch_certificate($options) {
    return efi_handle_file_upload($options, 'patch_certificate');
}

// Gerencia o upload do certificado de homologação
function efi_upload_patch_certificate_homologacao($options) {
    return efi_handle_file_upload($options, 'patch_certificate_homologacao');
}

// Função genérica para lidar com o upload de arquivos
function efi_handle_file_upload($options, $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['size'] > 0 && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        // Faz o upload do novo arquivo
        $file_info = pathinfo($_FILES[$field]['name']);
        $extension = $file_info['extension'] ?? '';
        $new_filename = "{$field}.{$extension}";

        // Realiza o upload do arquivo
        $upload = wp_upload_bits($new_filename, null, file_get_contents($_FILES[$field]['tmp_name']));
        if (!$upload['error']) {
            // Atualiza a URL do arquivo no banco de dados
            $options[$field] = $upload['url'];
            $options[$field.'_save'] = $upload['url'];
        } else {
            add_settings_error('efi_pagamentos_options', 'upload_error', 'Erro no upload: ' . $upload['error'], 'error');
        }
    } else {
        // Se não houver upload, mantém o valor antigo
        if (!isset($options[$field])) {
            $options[$field] = $options[$field.'_save'];  // Caso o valor não exista, mantém o valor vazio
        }
    }

    return $options;
}

// Atualiza as opções no banco de dados
function efi_pagamentos_save_uploaded_file($options) {
    $options = efi_upload_patch_certificate($options);
    $options = efi_upload_patch_certificate_homologacao($options);
    return $options;
}
add_filter('pre_update_option_efi_pagamentos_options', 'efi_pagamentos_save_uploaded_file');


// Função para excluir arquivos
function efi_pagamentos_delete_file() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissão negada.']);
    }

    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    if (!$field) {
        wp_send_json_error(['message' => 'Campo `field` não recebido.']);
    }

    $options = get_option('efi_pagamentos_options', []);
    if (!isset($options[$field])) {
        wp_send_json_error(['message' => 'Campo não encontrado nas opções.']);
    }

    $file_path = str_replace(get_site_url() . '/', ABSPATH, $options[$field]);
    if (file_exists($file_path)) unlink($file_path);

    unset($options[$field]);
    unset($options[$field.'_save']);

    if (update_option('efi_pagamentos_options', $options)) {
        wp_send_json_success(['message' => 'Arquivo excluído com sucesso.']);
    } else {
        wp_send_json_error(['message' => 'Erro ao atualizar as opções no banco de dados.']);
    }
}

add_action('wp_ajax_delete_file', 'efi_pagamentos_delete_file');


function efi_pagamentos_save_webhook_url() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permissão negada.']);
    }

    $webhook_url = isset($_POST['webhook_url']) ? sanitize_text_field($_POST['webhook_url']) : '';
    if (!$webhook_url) {
        wp_send_json_error(['message' => 'URL do Webhook não recebida.']);
    }

    $options = get_option('efi_pagamentos_options', []);
    $options['webhook'] = $webhook_url;

    if (update_option('efi_pagamentos_options', $options)) {
        wp_send_json_success(['message' => 'URL do Webhook salva com sucesso.']);
    } else {
        wp_send_json_error(['message' => 'Erro ao salvar a URL do Webhook.']);
    }
    
}
add_action('wp_ajax_save_webhook_url', 'efi_pagamentos_save_webhook_url');

add_action('rest_api_init', function () {
    error_log('rest_api_init chamado!');
    register_rest_route('efi/v1', '/criar-webhook', [
        'methods' => 'GET',
        'callback' => 'efi_criar_webhook_callback',
    ]);
    register_rest_route('efi/v1', '/webhook', [
        'methods' => ['POST', 'GET'],
        'callback' => 'efi_webhook_callback',
    ]);
});

function efi_get_options() {
    $options_cadastrados = get_option('efi_pagamentos_options', []);
    $environment = '';
    $sandbox = false;

    if ($options_cadastrados['environment'] == 'homologacao') {
        $environment = '_' . $options_cadastrados['environment'];
        $sandbox = true;
    }

    $options = [
        "client_id" => $options_cadastrados['client_id' . $environment],
        "client_secret" => $options_cadastrados['client_secret' . $environment],
        "certificate" => str_replace(get_site_url() . '/', ABSPATH, $options_cadastrados['patch_certificate' . $environment]),
        "sandbox" => $sandbox,
        "timeout" => $options_cadastrados['timeout']
    ];

    $chave_pix = $options_cadastrados['pix'];

    return [$options, $chave_pix, $options_cadastrados];
}

function efi_criar_webhook_callback(WP_REST_Request $request) {
    error_log('Rota /criar-webhook!');

    list($options, $chave_pix, $options_cadastrados) = efi_get_options();
    $options["headers"] = [
        "x-skip-mtls-checking" => "true",
    ];

    $params = [
        "chave" => $chave_pix
    ];

    $body = [
        "webhookUrl" => get_bloginfo('url') . "/wp-json/efi/v1/webhook?ignorar="
    ];

    try {
        $api = new EfiPay($options);
        $response = $api->pixConfigWebhook($params, $body);
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Webhook configurado com sucesso!',
            'data' => [
                'response' => $response,
                'info' => 'Configuração do webhook foi realizada com sucesso.',
            ],
        ], 200);

    } catch (EfiException $e) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Erro na API EFI.',
            'error' => [
                'code' => $e->code,
                'error' => $e->error,
                'description' => $e->errorDescription,
            ],
        ], 500);

    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Erro ao configurar webhook.',
            'error' => [
                'message' => $e->getMessage(),
            ],
        ], 500);
    }
}

function efi_webhook_callback(WP_REST_Request $request) {
    error_log('Rota /webhook chamada!');
    list($options, $chave_pix, $options_cadastrados) = efi_get_options();   
    $dados_webhook = file_get_contents("php://input");
    $dados_webhook = json_decode($dados_webhook);
    $body_email = "<p style='text-align:center;'><strong>COMPROVANTE DE COMPENSAÇÃO</strong><br><strong>Status:</strong> Pago</p>";
    $subject = "Comprovante de Compensação";
    
    if (isset($_POST["notification"])) {
        $params = [
            "token" => $_POST["notification"]
        ];        
        $api = new EfiPay($options);
        $response = $api->getNotification($params);   
        $i = count($response['data']);
        $ultimoStatus = $response['data'][$i - 1];
        $status = $ultimoStatus['status'];
        $charge_id = null;
        if (isset($ultimoStatus['identifiers']['charge_id'])) {
            $charge_id = $ultimoStatus['identifiers']['charge_id'];
            $statusAtual = $status['current'];
            $wp_query = new WP_Query(array(
                'post_type'      => $options_cadastrados['tipo_post'],
                'order' => 'DESC',
                'posts_per_page' => '1',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => $options_cadastrados['charge_id'],
                        'compare' => '=',
                        'value' => $charge_id
                    )
                )
            ));
            if ($wp_query->have_posts()) :
                while ($wp_query->have_posts()) : $wp_query->the_post();
                    $post_id = get_the_ID();
                endwhile;
            endif;
            wp_reset_query();
            if ($statusAtual == 'paid' || $statusAtual == 'approved') {                            
                update_post_meta($post_id, $options_cadastrados['status'], $statusAtual);
                update_post_meta($post_id, $options_cadastrados['data_pagamento'], current_time('Y-m-d'));
                update_post_meta($post_id, $options_cadastrados['pago'], true);
                update_post_meta($post_id, 'situacao_compensacao', 'Pago');
                $to = get_field('e-mail', $post_id);
                wp_mail($to, $subject, $body_email);
            }else{
                update_post_meta($post_id, $options_cadastrados['status'], $statusAtual);
            }
        }        
    }else if (isset($dados_webhook->pix)) {
        foreach ($dados_webhook->pix as $pix) {
            $params = [
                "txid" => $pix->txid
            ];            
            $api = EfiPay::getInstance($options);
            $response = $api->pixDetailCharge($params);
            if ($response['status'] == 'CONCLUIDA') {
                $wp_query = new WP_Query(array(
                    'post_type'      => $options_cadastrados['tipo_post'],
                    'order' => 'DESC',
                    'posts_per_page' => '1',
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'   => $options_cadastrados['txid'],
                            'compare' => '=',
                            'value' => $pix->txid
                        )
                    )
                ));
                if ($wp_query->have_posts()) :
                    while ($wp_query->have_posts()) : $wp_query->the_post();
                        $post_id = get_the_ID();
                    endwhile;
                endif;
                wp_reset_query();
                update_post_meta($post_id, $options_cadastrados['status'], $response['status']);
                update_post_meta($post_id, $options_cadastrados['data_pagamento'], $pix->horario);
                update_post_meta($post_id, $options_cadastrados['pago'], true);
                update_post_meta($post_id, 'situacao_compensacao', 'Pago');
                $to = get_field('e-mail', $post_id);
                wp_mail($to, $subject, $body_email);
            }
        }
    }
}
function exibir_formulario_pagamento_cartao() {
    ob_start();
    $id = $_GET['emissao_entidade_id'];
    $options_cadastrados = get_option('efi_pagamentos_options', []);
    $environment = $options_cadastrados['environment'] === 'homologacao' ? 'sandbox' : 'production';
    ?>
    <form method="post" class="formulario">
        <div class="mb-3">
            <label for="cartao_nome" class="form-label fw-bold">Nome no cartão</label>
            <input type="text" class="form-control" id="cartao_nome" name="cardholder" required placeholder="Nome completo">
        </div>
        <div class="mb-3">
            <label for="cartao_numero" class="form-label fw-bold">Número do cartão</label>
            <input type="text" class="form-control" id="cartao_numero" name="card_number" required placeholder="0000 0000 0000 0000">
        </div>
        <div class="row mb-3">
            <div class="col-md-4 col-6">
                <label for="cartao_validade_mes" class="form-label fw-bold">Validade Mês</label>
                <input type="text" class="form-control" id="cartao_validade_mes" name="expiration_month" required placeholder="MM">
            </div>
            <div class="col-md-4 col-6">
                <label for="cartao_validade_ano" class="form-label fw-bold">Validade Ano</label>
                <input type="text" class="form-control" id="cartao_validade_ano" name="expiration_year" required placeholder="AAAA">
            </div>
            <div class="col-md-4">
                <label for="cartao_cvv" class="form-label fw-bold">CVV</label>
                <input type="text" class="form-control" id="cartao_cvv" name="security_code" required placeholder="000">
            </div>
        </div>
        <div class="row mb-3" style="display:none;">
            <div class="col-md-6">
                <label for="cartao_nascimento" class="form-label fw-bold">CPF</label>
                <input required="" name="nascimento" type="text" class="form-control" id="cartao_nascimento" placeholder="99/99/9999" value="27/10/1988" inputmode="text">
            </div>  
            <div class="col-md-6">
                <label for="cpf" class="form-label fw-bold">CPF</label>
                <input type="text" name="cartao_cpf" class="form-control" id="cartao_cpf" value="007.534.481-57" required="" inputmode="text" placeholder="Número do documento">
            </div>        
            <div class="col-md-6" >
                <label class="form-label fw-bold" for="cpf">Telefone</label>
                <input required="" name="telefone" type="text" class="form-control" id="telefone" placeholder="(00) 0 0000-0000" value="67992859942">
            </div>
        </div>
        <div class="row mb-3" style="display:none;">
            <div class="col-md-4" >
                <label class="form-label fw-bold" for="cep">CEP</label>
                <input required="" name="cep" type="text" class="form-control" id="cep" placeholder="00000-000" value="79010-071">
            </div>
            <div class="col-md-6" >
                <label class="form-label fw-bold" for="rua">Rua</label>
                <input required="" name="rua" type="text" class="form-control" id="endereco" placeholder="Rua" value="Travessa das Paineiras">
            </div>
            <div class="col-md-2" >
                <label class="form-label fw-bold" for="numero">Número</label>
                <input required="" name="numero" type="number" class="form-control" id="numero" placeholder="Número" value="304">
            </div>
        </div>
        <div class="row mb-3" style="display:none;">
            <div class="col-md-4" >
                <label class="form-label fw-bold" for="bairro">Bairro</label>
                <input required="" name="bairro" type="text" class="form-control" id="bairro" placeholder="Bairro" value="Monte Castelo">
            </div>
            <div class="col-md-4" >
                <label class="form-label fw-bold" for="estado">Estado</label>
                <input required="" name="estado" type="text" class="form-control"  placeholder="Estado" value="MS" id="aluno_estado">
            </div>
            <div class="col-md-4" >
                <label class="form-label fw-bold" for="cidade">Cidade</label>
                <input required="" name="cidade" type="text" class="form-control"  placeholder="Cidade" value="Campo Grande" id="aluno_cidade" >
            </div>
        </div>
        <input type="hidden" name="metodo_pagamento" value="cartao"/>
        <button type="button" class="btn btn-primary w-100 btn_pagar_cartao">Efetuar pagamento</button>
    </form>

    <script src="https://cdn.jsdelivr.net/gh/efipay/js-payment-token-efi/dist/payment-token-efi.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        jQuery.noConflict();
        (function($) {
            function gerarToken(cartao_numero, cvv, expirationMonth, expirationYear) {
                const originalText = $('.btn_pagar_cartao').text();
                $('.btn_pagar_cartao').text('Carregando...').prop('disabled', true);

                try {
                    EfiJs.CreditCard.setCardNumber(cartao_numero)
                        .verifyCardBrand()
                        .then(brand => {
                            if (brand) {
                                EfiJs.CreditCard.setAccount('<?php echo $options_cadastrados['account']; ?>')
                                    .setEnvironment('<?php echo $environment; ?>')
                                    .setCreditCardData({
                                        brand,
                                        number: cartao_numero,
                                        cvv,
                                        expirationMonth,
                                        expirationYear,
                                        reuse: true
                                    })
                                    .getPaymentToken()
                                    .then(data => {
                                        const payment_token = data.payment_token;
                                        const card_mask = data.card_mask;

                                        const form = $(".formulario");
                                        form.append('<input type="hidden" name="payment_token" value="' + payment_token + '">');
                                        form.append('<input type="hidden" name="card_mask" value="' + card_mask + '">');
                                        form.submit();
                                    })
                                    .catch(err => {
                                        console.error('Erro ao processar pagamento:', err);
                                        alert('Erro ao processar pagamento. Verifique os dados e tente novamente.');
                                        $('.btn_pagar_cartao').text(originalText).prop('disabled', false);
                                    });
                            }
                        })
                        .catch(err => {
                            console.error('Erro ao verificar a bandeira do cartão:', err);
                            alert('Erro ao verificar a bandeira do cartão.');
                            $('.btn_pagar_cartao').text(originalText).prop('disabled', false);
                        });
                } catch (error) {
                    console.error('Erro inesperado:', error);
                    alert('Erro inesperado. Tente novamente.');
                    $('.btn_pagar_cartao').text(originalText).prop('disabled', false);
                }
            }

            $(document).ready(function () {
                $('.btn_pagar_cartao').click(function() {
                    const form = $(".formulario");
                    const isValid = form[0].checkValidity();
                    form.addClass('was-validated');

                    if (isValid) {
                        gerarToken(
                            $('#cartao_numero').val().replace(/\s/g, ""),
                            $('#cartao_cvv').val(),
                            $('#cartao_validade_mes').val(),
                            $('#cartao_validade_ano').val()
                        );
                    } else {
                        alert('Por favor, preencha todos os campos obrigatórios.');
                    }
                });
            });
        })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}


function shortcode_formulario_pagamento_cartao() {
    return exibir_formulario_pagamento_cartao(); // Chama a função que retorna o HTML do formulário
}
add_shortcode('formulario_pagamento_cartao', 'shortcode_formulario_pagamento_cartao');


function exibir_formulario_pagamento_pix() {
    ob_start();
    $id = $_GET['emissao_entidade_id'];
    ?>
    <form method="post" class="formulario_pix">
        <div class="mb-3">
            <label for="nomerazaosocial" class="form-label fw-bold">Nome</label>
            <input type="text" class="form-control" id="nomerazaosocial" placeholder="Nome" name="nomerazaosocial"/ required>
        </div>
        <div class="mb-3">
            <label for="cpfcnpj" class="form-label fw-bold">CPF</label>
            <input type="text" class="form-control" id="cpfcnpj" placeholder="CPF" name="cpfcnpj" required>
        </div>
        <input type="hidden" name="metodo_pagamento" value="pix"/>
        <button type="button" class="btn btn-primary w-100 btn_pagar_pix">Efetuar pagamento</button>
    </form>
    <script>
        jQuery.noConflict();
        (function($) {            
            $(document).ready(function () {
                $('.btn_pagar_pix').click(function() {
                    const originalText = $('.btn_pagar_pix').text();
                    $('.btn_pagar_pix').text('Carregando...').prop('disabled', true);
                    const form = $(".formulario_pix");
                    const isValid = form[0].checkValidity();
                    form.addClass('was-validated');
                    if (!isValid) {
                        alert('Por favor, preencha todos os campos obrigatórios.');
                        $('.btn_pagar_pix').text(originalText).prop('disabled', false);
                    }else{
                        form.submit();
                    }
                });
            });
        })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

function shortcode_formulario_pagamento_pix() {
    return exibir_formulario_pagamento_pix(); // Chama a função que retorna o HTML do formulário
}
add_shortcode('formulario_pagamento_pix', 'shortcode_formulario_pagamento_pix');

function processar_pagamento_cartao() {
    list($options, $chave_pix, $options_cadastrados) = efi_get_options();  
    $post_id =$_GET['emissao_entidade_id'];
    $valor = get_field($options_cadastrados['valor'],$post_id);
    $valor_inteiro = str_replace(".", "", $valor);
    
    $items = [
        [
            "name" => get_the_title($post_id),
            "amount" => 1,
            "value" => (int) $valor_inteiro
        ]
    ];
    
    $paymentToken = $_REQUEST['payment_token']; 
    $customer = [
        "name" => $_REQUEST['cardholder'],
        "cpf" => preg_replace('/[^0-9]/', '', $_REQUEST['cartao_cpf']),
        "phone_number" => preg_replace('/[^0-9]/', '', $_REQUEST['telefone']),
        "email" => get_field('e-mail',$post_id),
        "birth" => DateTime::createFromFormat('d/m/Y', $_REQUEST['nascimento'])->format('Y-m-d')
    ];
    
    //$current_user->user_email
    $billingAddress = [
        "street" =>  $_REQUEST['rua'], 
        "number" => preg_replace('/[^0-9]/', '', $_REQUEST['numero']),
        "neighborhood" => $_REQUEST['bairro'],
        "zipcode" => preg_replace('/[^0-9]/', '', $_REQUEST['cep']),
        "city" => $_REQUEST['cidade'],
        "state" => $_REQUEST['estado'],
    ];
    $body = [
        "items" => $items,
        "payment" => [
            "credit_card" => [
                "billing_address" => $billingAddress,
                "payment_token" => $paymentToken,
                "customer" => $customer
            ]
        ],
        "metadata" => [
            "notification_url" => get_bloginfo('url') . "/wp-json/efi/v1/webhook"
        ]
    ];
    
    try {
        $api = new EfiPay($options);
        $response = $api->createOneStepCharge($params = [], $body);
        if ($response['code'] == 200 && ($response['data']['status']=='paid' ||  $response['data']['status']=='approved')) {  
            update_post_meta($post_id, $options_cadastrados['data_criacao'], current_time('Y-m-d'));
            update_post_meta($post_id, $options_cadastrados['forma_pagamento'], 'cartao');
            update_post_meta($post_id, $options_cadastrados['status'], 'PENDING');
            update_post_meta($post_id, $options_cadastrados['charge_id'], $response['data']['charge_id']);
            $redirect_url = 'checkout-pagamento-emissao-entidade/?emissao_entidade_id=' . $post_id . '&cartao=1&evento='.$_GET['evento'];
            wp_redirect(home_url($redirect_url));
            exit();
        }else{
            return 'Erro pagamento não efetuado';
        }
    } catch (EfiException $e) {
        return 'Erro: ' . $e->error . ' - ' . $e->errorDescription;          
    } catch (Exception $e) {
        return 'Erro desconhecido: ' . $e->getMessage();
    }
}

function processar_pagamento_pix() {    
    list($options, $chave_pix, $options_cadastrados) = efi_get_options();  
    $valor = get_field($options_cadastrados['valor'],$_GET['emissao_entidade_id']);
    $post_id =$_GET['emissao_entidade_id'];
    if(strlen(preg_replace('/[^0-9]/', '', $_REQUEST['cpfcnpj']))>11){
        $customer = [
            "cnpj" => preg_replace('/[^0-9]/', '', $_REQUEST['cpfcnpj']),
            "nome" => $_REQUEST['nomerazaosocial']
        ];
    }else{
        $customer = [
            "cpf" => preg_replace('/[^0-9]/', '', $_REQUEST['cpfcnpj']),
            "nome" => $_REQUEST['nomerazaosocial']
        ];
    }
    $body = [
        "calendario" => [ 
            "expiracao" => (int) 3600
        ],
        "devedor" => $customer,
        "valor" => [
            "original" => $valor 
        ],
        "chave" => $chave_pix
    ];
    try {
        $api = new EfiPay($options);
        $pix = $api->pixCreateImmediateCharge($params = [], $body); // Using this function the txid will be generated automatically by Efí API
        
        if (!empty($pix['txid'])) {
            $data = new DateTime($pix['calendario']['criacao']);
            $date_of_expiration = $data->add(new DateInterval('PT'.$pix['calendario']['expiracao'].'S'));
            $params = [
                'id' => $pix['loc']['id']
            ];
            // Gera QRCode
            $qrcode = $api->pixGenerateQRCode($params);
            $response = [
                "code" => 200,
                "pix" => $pix,
                "qrcode" => $qrcode,
                "txid" => $pix['txid'],
                "date_of_expiration" => $date_of_expiration->format('Y-m-d H:i:s')
            ];
            update_post_meta($post_id, $options_cadastrados['data_criacao'], $pix['calendario']['criacao']);
            update_post_meta($post_id, $options_cadastrados['forma_pagamento'], 'pix');
            update_post_meta($post_id, $options_cadastrados['status'], 'PENDING');
            update_post_meta($post_id, $options_cadastrados['date_of_expiration'], $response['date_of_expiration']);
            update_post_meta($post_id, $options_cadastrados['qr_code_base64'], $response['qrcode']['imagemQrcode']);
            update_post_meta($post_id, $options_cadastrados['qr_code'], $response['qrcode']['qrcode']);
            update_post_meta($post_id, $options_cadastrados['txid'], $pix['txid']);
            $redirect_url = 'checkout-pagamento-emissao-entidade/?emissao_entidade_id=' . $post_id . '&pix=1&evento='.$_GET['evento'];
            wp_redirect(home_url($redirect_url));
            exit();
        } else {
            // Caso não tenha txid, retorna erro
            return 'Erro ao tentar criar pix. Tente novamente.';
        }
    } catch (EfiException $e) {
        return 'Erro: ' . $e->error . ' - ' . $e->errorDescription; 
    } catch (Exception $e) {
        return 'Erro desconhecido: ' . $e->getMessage();
    }
}

function processar_pagamento() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['metodo_pagamento'])) {
        $metodo = sanitize_text_field($_POST['metodo_pagamento']);
        $resultado = '';
        
        if ($metodo === 'cartao') {
            $resultado = processar_pagamento_cartao();
        } elseif ($metodo === 'pix') {
            $resultado = processar_pagamento_pix();
        }
        add_action('wp_footer', function() use ($resultado) { 
            echo '<script>alert("' . esc_js($resultado) . '");</script>';
        });
    }
}
add_action('init', 'processar_pagamento');

function inputmask_enqueue_script() {
    $input_mask_js_path = plugin_dir_url(__FILE__) . 'js/jquery.inputmask.min.js';
    wp_enqueue_script(
        'jquery-inputmask', 
        $input_mask_js_path,
        ['jquery'], // Dependência do jQuery
        '5.0.8',
        true // Carregar no final do body para otimizar desempenho
    );
    $custom_js_path = plugin_dir_url(__FILE__) . 'js/custom.js';

    // Adiciona o script custom.js
    wp_enqueue_script(
        'custom-js',
        $custom_js_path,
        ['jquery-inputmask'], // Dependência do jQuery
        '1.0',
        true // Carregar no final do body
    );
}

// Hook para carregar o script no frontend
add_action('wp_enqueue_scripts', 'inputmask_enqueue_script');