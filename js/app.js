// Função para alternar o menu off-screen (hamburguer menu)
function toggleMenu() {
    const menu = document.querySelector('.off-screen-menu');
    const hamMenu = document.querySelector('.ham-menu');

    menu.classList.toggle('show');
    hamMenu.classList.toggle('active'); // Adiciona ou remove a classe active
}

// Lógica do dropdown
var dropdownButton = document.getElementById('dropdownButton');
var dropdownContent = document.getElementById('dropdownContent');

// Impede que o dropdown feche ao clicar no botão
dropdownButton.addEventListener('click', function(event) {
    event.stopPropagation(); // Impede que o clique no botão propague para o window
    dropdownContent.classList.toggle('show'); // Alterna a visibilidade do dropdown
});

// Fecha o dropdown ao clicar fora do botão ou do conteúdo do dropdown
window.addEventListener('click', function(event) {
    // Verifica se o clique foi fora do dropdownButton ou do dropdownContent
    if (!dropdownButton.contains(event.target) && !dropdownContent.contains(event.target)) {
        dropdownContent.classList.remove('show'); // Remove a classe 'show' e esconde o dropdown
    }
});

// Impede que o clique no hambúrguer menu afete o dropdown
var hamMenuButton = document.querySelector('.ham-menu');
hamMenuButton.addEventListener('click', function(event) {
    event.stopPropagation(); // Impede que o clique no hambúrguer menu se propague para o window
});
