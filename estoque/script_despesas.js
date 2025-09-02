document.addEventListener('DOMContentLoaded', function() {
    const despesaForm = document.getElementById('despesa-form');
    const materialSelect = document.getElementById('material');
    const totalDespesasElement = document.getElementById('total_despesas');
    const lucroElement = document.getElementById('lucro');

    // Função para carregar materiais no select
    function loadMateriais() {
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
            });
    }

    // Função para carregar o total de despesas
    function loadTotalDespesas() {
        fetch('fetch_total_despesas.php')
            .then(response => response.json())
            .then(data => {
                const valor = parseFloat(data.total_despesas) || 0;
                totalDespesasElement.textContent = `Total despesas: R$ ${valor.toFixed(2).replace('.', ',')}`;
            })
            .catch(error => {
                console.error("Erro ao carregar total de despesas:", error);
                totalDespesasElement.textContent = `Total despesas: R$ 0,00`;
            });
    }

    // Função para carregar o lucro
    function loadLucro() {
        fetch('fetch_lucro.php')
            .then(response => response.json())
            .then(data => {
                const lucro = parseFloat(data.lucro) || 0;
                lucroElement.textContent = `Lucro: R$ ${lucro.toFixed(2).replace('.', ',')}`;
            })
            .catch(error => {
                console.error("Erro ao carregar lucro:", error);
                lucroElement.textContent = `Lucro: R$ 0,00`;
            });
    }

    // Adicionar despesa manual
    if (despesaForm) {
        despesaForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(despesaForm);
            fetch('add_despesa_manual.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                despesaForm.reset();
                loadTotalDespesas();
                loadLucro();
            });
        });
    }

    loadMateriais();
    loadTotalDespesas();
    loadLucro();
    
    // Recarregar a cada 30 segundos para manter atualizado
    setInterval(function() {
        loadTotalDespesas();
        loadLucro();
    }, 30000);
});
