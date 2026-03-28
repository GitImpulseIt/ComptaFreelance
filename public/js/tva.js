/**
 * Formulaire 3514 - Calcul automatique des cases TVA
 */
document.addEventListener('DOMContentLoaded', function () {

    function recalculer(echId) {
        var form = document.getElementById('form-' + echId);
        if (!form) return;

        var val = function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            return input ? (parseInt(input.value) || 0) : 0;
        };
        var setVal = function (name, v) {
            var input = form.querySelector('[name="' + name + '"]');
            if (input) input.value = v;
        };

        var case01 = val('case_01');
        var case02 = val('case_02');
        var case05 = val('case_05');
        var case06 = val('case_06');
        var case08 = val('case_08');

        var case03 = Math.max(0, case01 - case02);
        var case07 = Math.max(0, case06 - case05);

        setVal('case_03', case03);
        setVal('case_07', case07);

        // Calcul du montant final et message
        var montantFinal = 0;
        var message = '';
        var couleur = '#64748b'; // slate

        if (case03 > 0) {
            montantFinal = case03;
            message = "Montant de l'acompte \u00e0 payer";
            couleur = '#b45309'; // amber-700
        } else if (case07 > 0) {
            if (case08 > 0 && case08 <= case07) {
                montantFinal = 0;
                message = 'Remboursement sollicit\u00e9 : ' + formatMontant(case08);
                couleur = '#4338ca'; // indigo-700
            } else {
                montantFinal = 0;
                message = 'Cr\u00e9dit de TVA report\u00e9 : ' + formatMontant(case07);
                couleur = '#4338ca';
            }
        } else {
            montantFinal = 0;
            message = 'Aucun montant \u00e0 r\u00e9gler';
            couleur = '#64748b';
        }

        var montantEl = document.getElementById('montant-du-' + echId);
        var labelEl = document.getElementById('resume-label-' + echId);

        if (montantEl) {
            montantEl.textContent = formatMontant(montantFinal);
            montantEl.style.color = montantFinal > 0 ? '#b45309' : '#4338ca';
        }
        if (labelEl) {
            labelEl.textContent = message;
        }

        // Validation case 08 <= case 07
        var case08Input = form.querySelector('[name="case_08"]');
        if (case08Input) {
            if (case08 > case07 && case07 > 0) {
                case08Input.classList.add('ring-2', 'ring-red-400');
                case08Input.title = 'Le remboursement ne peut pas d\u00e9passer le cr\u00e9dit disponible (' + formatMontant(case07) + ')';
            } else {
                case08Input.classList.remove('ring-2', 'ring-red-400');
                case08Input.title = '';
            }
        }
    }

    function formatMontant(montant) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(montant);
    }

    // Attacher les événements sur les champs modifiables
    document.querySelectorAll('.tva-case').forEach(function (input) {
        input.addEventListener('input', function () {
            recalculer(this.dataset.ech);
        });

        input.addEventListener('blur', function () {
            var v = parseInt(this.value);
            if (isNaN(v) || v < 0) this.value = '0';
            else this.value = v.toString();
            recalculer(this.dataset.ech);
        });
    });

    // Calcul initial au chargement
    var seen = {};
    document.querySelectorAll('.tva-case[data-ech]').forEach(function (input) {
        var id = input.dataset.ech;
        if (!seen[id]) {
            seen[id] = true;
            recalculer(id);
        }
    });
});
