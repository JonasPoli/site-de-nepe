/*
 * Mídia que só carrega quando faz falta: imagens de carrossel e o mapa.
 *
 * Por que existe: num carrossel, todos os slides ficam empilhados no mesmo lugar da tela,
 * então o navegador considera todos visíveis e o `loading="lazy"` não segura nada — numa
 * home com 8 banners isso eram 780 KB disputando banda com a primeira imagem, e o
 * Speed Index ia de 1,3 s para 7 s. Só o primeiro slide vem com `src` no HTML; os demais
 * guardam o endereço em `data-adiada` e são promovidos aqui, depois do `load`.
 */
(function () {
    'use strict';

    function promover() {
        var adiadas = document.querySelectorAll('img[data-adiada]');
        Array.prototype.forEach.call(adiadas, function (img) {
            var srcset = img.getAttribute('data-adiada-srcset');
            if (srcset) {
                img.setAttribute('sizes', img.getAttribute('data-adiada-sizes') || '100vw');
                img.setAttribute('srcset', srcset);
            }
            img.setAttribute('src', img.getAttribute('data-adiada'));
            img.removeAttribute('data-adiada');
            img.removeAttribute('data-adiada-srcset');
        });
    }

    function agendar() {
        // `requestIdleCallback` espera a linha principal ficar livre; sem ele, um respiro.
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(promover, { timeout: 2000 });
        } else {
            window.setTimeout(promover, 200);
        }
    }

    if (document.readyState === 'complete') {
        agendar();
    } else {
        window.addEventListener('load', agendar, { once: true });
    }

    /*
     * Mapa: o iframe do Google Maps puxa cerca de 330 KB de JavaScript de terceiro, e o
     * `loading="lazy"` do navegador o solta cedo demais numa tela alta — ele disputava
     * banda com a imagem do topo. Aqui ele entra quando chega perto da tela, então quem
     * rola até o mapa continua vendo o mapa, sem clicar em nada.
     */
    var mapas = document.querySelectorAll('iframe[data-adiada]');
    if (mapas.length) {
        var carregar = function (quadro) {
            quadro.setAttribute('src', quadro.getAttribute('data-adiada'));
            quadro.removeAttribute('data-adiada');
        };

        if (typeof IntersectionObserver === 'undefined') {
            Array.prototype.forEach.call(mapas, carregar);
        } else {
            var vigia = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada) {
                    if (entrada.isIntersecting) {
                        carregar(entrada.target);
                        vigia.unobserve(entrada.target);
                    }
                });
            }, { rootMargin: '400px' });

            Array.prototype.forEach.call(mapas, function (quadro) {
                vigia.observe(quadro);
            });
        }
    }

    // Se o visitante mexer no carrossel antes disso, as imagens entram na hora.
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evento) {
        window.addEventListener(evento, promover, { once: true, passive: true });
    });
})();
