document.addEventListener('DOMContentLoaded', function() {
    const materiaisList = document.getElementById('materiais-list');

    // Função para carregar materiais
    function loadMateriais() {
        fetch('fetch_materiais.php')
            .then(response => response.json())
            .then(data => {
                materiaisList.innerHTML = '';
                data.forEach(material => {
                    const li = document.createElement('li');
                    li.textContent = `${material.descricao} - Quantidade: ${material.quantidade} - Valor Estoque: R$ ${material.valor_unitario_estoque} - Valor Venda Estimado: R$ ${material.valor_unitario_venda_estimado}`;
                    materiaisList.appendChild(li);
                });
            });
    }

    loadMateriais();
    
    // Recarregar a cada 30 segundos para manter atualizado
    setInterval(loadMateriais, 30000);
});
