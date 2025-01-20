
<?php 
    
    if (get_field('pago',$_GET['emissao_entidade_id'])) { 
    $redirecionar_para = '/?post_type=emissao_entidade&p=' . $_GET['emissao_entidade_id'].'&evento='.$_GET['evento']; 
    wp_redirect(home_url('/' . $redirecionar_para)); 
    
    }
    ?>

    <!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .cursor-pointer {
          cursor: pointer;
        }
      </style>
      <?php wp_head(); 
      
      ?>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <!-- Detalhes do Pedido -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Detalhes da compesação</h5>
                        <p class="mb-2">Total da compensação: <strong>R$ <?php echo number_format((float) get_field('valor_compensacao',$_GET['emissao_entidade_id']), 2, ",", ".");?></strong></p>
                        <p class="text-success mb-0">Você está em um ambiente seguro.</p>
                    </div>
                </div>
            </div>

            <!-- Pagamento -->
            <div class="col-md-8">
                <h5 class="mb-4 fw-bold">Pagamento compensação</h5>
                <?php 
                if(!empty(get_field('txid',$_GET['emissao_entidade_id'])) && $_GET['pix']==1){                    
                    ?>
                <div class="row d-flex">
                    <div class="col-md-10 mt-4">
                        <div class="row d-flex align-items-center justify-content-center">
                            <script>
                                function myFunction() {
                                    var copyText = document.getElementById("copia_cola");
                                    copyText.select();
                                    copyText.setSelectionRange(0, 99999);
                                    navigator.clipboard.writeText(copyText.value);
                                    alert("Copiado");
                                }
                            </script>  
                            <div class="col-md-4 text-center">
                                <img style='display:block;    width: 100%;' id='base64image' src='<?php echo get_field('qr_code_base64',$_GET['emissao_entidade_id'])?>' />
                                <input style='display:none;' type="text" value="<?php echo get_field('qr_code',$_GET['emissao_entidade_id']);?>" id="copia_cola">
                                <a class="btn mt-2 btn-primary btn-sample" href="javascript:void(0)" onclick="myFunction();">Pix Copia e Cola</a>
                            </div>
                            <div class="col-md-8">
                                <h2 class="font-16 font-weight-bold">Pague com Pix e receba a confirmação imediata do seu pagamento:</h2>
                                <ol>
                                    <li>
                                        <p class="font-16">Abra o aplicativo do seu banco de preferência</p>
                                    </li>
                                    <li>
                                        <p class="font-16">Selecione a opção <strong>pagar com Pix</strong></p>
                                    </li>
                                    <li>
                                        <p class="font-16">Leia o QR code ou copie o código abaixo e cole no campo de pagamento</p>
                                    </li>
                                </ol>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <strong>Você tem <span id="countdown">2:00</span> minutos para concluir o pagamento.</strong>
                        </div>
                    </div>
                </div>
                
                <script>
                    let timeLeft = 120; 
                    function startCountdown() {
                        const countdownElement = document.getElementById('countdown');
                        const timerInterval = setInterval(() => {
                            const minutes = Math.floor(timeLeft / 60);
                            const seconds = timeLeft % 60;
                            countdownElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                            timeLeft--;
                            if (timeLeft < 0) {
                                clearInterval(timerInterval);
                                window.location.href = "<?php echo home_url() . '/?post_type=emissao_entidade&p=' . $_GET['emissao_entidade_id'].'&evento='.$_GET['evento'];?>"; 
                            }
                        }, 1000);
                    }
                    document.addEventListener('DOMContentLoaded', startCountdown);
                </script>
            <?php 
                }else if($_GET['cartao']==1){
                    ?>
                    <div class="col-md-12 text-center">
                        <img width="100" class="img-fluid mb-4" src="<?php bloginfo('template_url');?>/images/checked.png" alt="">
                        <br/>
                        <p class="text-center"> <strong class="text-success font-28 text-uppercase">Pagamento processado com sucesso, aguarde confirmação de pagamento! </strong></p>
                        <strong class="mt-4">Em <span id="countdown">10</span> segundos será direcionado.</strong>
                    </div>
                    <script>
                        let timeLeft = 10; 
                        function startCountdown() {
                            const countdownElement = document.getElementById('countdown');
                            const timerInterval = setInterval(() => {
                                const minutes = Math.floor(timeLeft / 60);
                                const seconds = timeLeft % 60;
                                countdownElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                                timeLeft--;
                                if (timeLeft < 0) {
                                    clearInterval(timerInterval);
                                    window.location.href = "<?php echo home_url() . '/?post_type=emissao_entidade&p=' . $_GET['emissao_entidade_id'].'&evento='.$_GET['evento'];?>"; 
                                }
                            }, 1000);
                        }
                        document.addEventListener('DOMContentLoaded', startCountdown);
                    </script>
            <?php
                }else{
                ?>
                <div id="accordion">
                    <div class="card card-body mb-3">
                      <div id="headingOne" class="d-flex justify-content-between align-items-center cursor-pointer" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        <h6 class="card-title fw-bold mb-0">Cartão de Crédito</h6>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-credit-card" viewBox="0 0 16 16">
                          <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0V4z"/>
                          <path d="M0 7v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7H0zm2 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2A.5.5 0 0 1 2 9zm0 2a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 0 1H2.5a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                      </div>
                  
                      <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordion">
                        <div>
                            <?php echo do_shortcode('[formulario_pagamento_cartao]');?>
                        </div>
                      </div>
                    </div>
                    <div class="card card-body">
                      <div id="headingTwo" class="d-flex justify-content-between align-items-center cursor-pointer" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        <h6 class="card-title fw-bold mb-0">PIX</h6>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-qr-code" viewBox="0 0 16 16">
                          <path d="M2 2h2v2H2V2Zm1 1h1V3H3v1ZM5 2h2v2H5V2Zm1 1h1V3H6v1ZM2 5h2v2H2V5Zm1 1h1V6H3v1ZM5 5h2v2H5V5Zm1 1h1V6H6v1ZM7 7h2v2H7V7Zm1 1h1V8H8v1ZM9 2h2v2H9V2Zm1 1h1V3h-1v1ZM2 9h2v2H2V9Zm1 1h1v-1H3v1ZM11 5h2v2h-2V5Zm1 1h1V6h-1v1ZM5 9h2v2H5V9Zm1 1h1v-1H6v1ZM9 9h2v2H9V9Zm1 1h1v-1h-1v1ZM12 9h2v2h-2V9Zm1 1h1v-1h-1v1ZM11 11h2v2h-2v-2Zm1 1h1v-1h-1v1ZM9 12h2v2H9v-2Zm1 1h1v-1h-1v1ZM7 12h2v2H7v-2Zm1 1h1v-1H8v1ZM5 12h2v2H5v-2Zm1 1h1v-1H6v1ZM2 12h2v2H2v-2Zm1 1h1v-1H3v1ZM12 2h2v2h-2V2Zm1 1h1V3h-1v1ZM9 5h2v2H9V5Zm1 1h1V6h-1v1ZM12 5h2v2h-2V5Zm1 1h1V6h-1v1Z"/>
                        </svg>
                      </div>
                      <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordion">
                        <div >
                          <?php echo do_shortcode('[formulario_pagamento_pix]');?>
                        </div>
                      </div>
                    </div>
                  </div>
              <?php 
                }
                ?>
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>
    
</body>
</html>