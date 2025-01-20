jQuery(document).ready(function ($) {
  $('#cartao_numero').inputmask('9999 9999 9999 9999');
  $('#cartao_validade_mes').inputmask('99');
  $('#cartao_validade_ano').inputmask('9999');
  $('#cartao_cvv').inputmask('999');
  $('#cartao_cpf').inputmask('999.999.999-99');
  $('#cep').inputmask('99999-999');
  $('#telefone').inputmask('(99) 9 9999-9999');
  $("input[id*='cpfcnpj']").inputmask({
      mask: ['999.999.999-99', '99.999.999/9999-99'],
      keepStatic: true
  });
  $("#cartao_cep").blur(function() {
    $("#cartao_endereco").val('');
    $("#cartao_bairro").val('');
    var cep = $(this).val().replace(/[^0-9]/, '');
    if (cep !== "") {
        var url = 'https://viacep.com.br/ws/' + cep + '/json/';
        $.getJSON(url, function(json) {
          console.log(json);
            //Atribuimos o valor aos inputs
            $("#cartao_endereco").val(json.logradouro);
            $("#cartao_bairro").val(json.bairro);
            $("#aluno_estado").val(json.uf);
            $("#aluno_cidade").val(json.localidade);
            $('#cartao_numero').focus();
        }).fail(function() {
            $("#cartao_endereco").val('');
            $("#cartao_bairro").val('');
        });
    }
});
});