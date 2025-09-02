document.addEventListener('DOMContentLoaded', function() {
    const transacaoForm = document.getElementById('transacao-form');
    const materialSelect = document.getElementById('material');
    const quantidadeDisponivelElement = document.getElementById('quantidade_disponivel');

    function fetchMateriais() {
        fetch('fetch_materiais.php')
            .then(response => response.json())
            .then(data => {
                materialSelect.innerHTML = '';
                data.forEach(material => {
                    const option = document.createElement('option');
                    option.value = material.id;
                    option.textContent = material.descricao;
                    materialSelect.appendChild(option);
                });
                updateQuantidadeDisponivel();
            });
    }

    function updateQuantidadeDisponivel() {
        const materialId = materialSelect.value;
        fetch(`fetch_quantidade.php?material_id=${materialId}`)
            .then(response => response.json())
            .then(data => {
                quantidadeDisponivelElement.textContent = `Quantidade disponível: ${data.quantidade}`;
            });
    }

    transacaoForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(transacaoForm);
        fetch('add_transacao.php', {
            method: 'POST',
            body: formData
        }).then(response => response.text())
          .then(data => {
              alert(data);
              transacaoForm.reset();
              updateQuantidadeDisponivel();
              fetchMateriais(); // Atualizar a lista de materiais
          });
    });

    materialSelect.addEventListener('change', updateQuantidadeDisponivel);

    fetchMateriais();
});

document.addEventListener('DOMContentLoaded', function() {
    const lucroElement = document.getElementById('lucro');

    // Função para carregar o lucro
    function loadLucro() {
        fetch('fetch_lucro.php')
            .then(response => response.json())
            .then(data => {
                lucroElement.textContent = `Lucro: R$ ${data.lucro.toFixed(2)}`;
            });
    }

    loadLucro();
});
