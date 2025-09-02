document.addEventListener('DOMContentLoaded', function() {
    const materialForm = document.getElementById('material-form');
    const updateMaterialForm = document.getElementById('update-material-form');
    const materialSelect = document.getElementById('material');

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

    // Adicionar material
    materialForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(materialForm);
        fetch('add_material.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            materialForm.reset();
            loadMateriais();
        });
    });

    // Atualizar material
    updateMaterialForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(updateMaterialForm);
        fetch('update_material.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            updateMaterialForm.reset();
            loadMateriais();
        });
    });

    loadMateriais();
});
