/*
 * Carrega o Swiper fora do caminho crítico e avisa quem precisa dele.
 *
 * Por que existe: o Swiper são 40 KB de JavaScript que não participam da primeira
 * pintura — o primeiro slide já aparece com as quatro regras de CSS que estão no
 * app.css. Carregado com `defer`, ele ainda disputava banda com a imagem do topo
 * ("reduza o JavaScript não usado — 24 KiB" no PageSpeed). Aqui ele entra depois do
 * `load`, e os blocos de banner esperam o evento `swiper:pronto` para iniciar.
 *
 * O endereço do arquivo vem do atributo `data-swiper` deste próprio <script>, porque
 * o nome tem impressão digital do AssetMapper e muda a cada build.
 */
(function () {
    'use strict';

    var esteScript = document.currentScript;
    var endereco = esteScript && esteScript.getAttribute('data-swiper');
    if (!endereco) {
        return;
    }

    var carregado = false;

    function carregar() {
        if (carregado) {
            return;
        }
        carregado = true;

        var script = document.createElement('script');
        script.src = endereco;
        script.addEventListener('load', function () {
            document.dispatchEvent(new CustomEvent('swiper:pronto'));
        });
        script.addEventListener('error', function () {
            // Sem o Swiper o primeiro slide continua na tela; só não há rotação.
            document.dispatchEvent(new CustomEvent('swiper:pronto'));
        });
        document.head.appendChild(script);
    }

    function agendar() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(carregar, { timeout: 1500 });
        } else {
            window.setTimeout(carregar, 150);
        }
    }

    if (document.readyState === 'complete') {
        agendar();
    } else {
        window.addEventListener('load', agendar, { once: true });
    }

    // Se o visitante tocar no carrossel antes disso, o Swiper entra na hora.
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evento) {
        window.addEventListener(evento, carregar, { once: true, passive: true });
    });
})();
