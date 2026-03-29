document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('crForm');

    function v(id) {
        var el = document.getElementById('c' + id);
        return el ? (parseFloat(el.value.replace(/\s/g, '').replace(',', '.')) || 0) : 0;
    }

    function s(id, val) {
        var el = document.getElementById('c' + id);
        if (el) el.value = val !== 0 ? Math.round(val).toString() : '';
    }

    function sum(keys) {
        var total = 0;
        for (var i = 0; i < keys.length; i++) total += v(keys[i]);
        return total;
    }

    function compute() {
        // Total produits exploitation (I)
        var produits = ['210','214','218','222','224','226','230'];
        s('232', sum(produits));
        s('232_n1', sum(produits.map(function(k) { return k + '_n1'; })));

        // Total charges exploitation (II)
        var charges = ['234','236','238','240','242','244','250','252','254','256','262'];
        s('264', sum(charges));
        s('264_n1', sum(charges.map(function(k) { return k + '_n1'; })));

        // 1 – Résultat exploitation (I – II)
        s('270', v('232') - v('264'));
        s('270_n1', v('232_n1') - v('264_n1'));

        // 2 – Bénéfice ou perte = (I + III + IV) – (II + V + VI + VII)
        var benefice = (v('232') + v('280') + v('290')) - (v('264') + v('294') + v('300') + v('306'));
        s('310', benefice);
        var beneficeN1 = (v('232_n1') + v('280_n1') + v('290_n1')) - (v('264_n1') + v('294_n1') + v('300_n1') + v('306_n1'));
        s('310_n1', beneficeN1);

        // Reporter bénéfice/déficit comptable
        if (benefice >= 0) {
            s('312', benefice);
            s('314', 0);
        } else {
            s('312', 0);
            s('314', -benefice);
        }

        // Résultat fiscal avant imputation
        var reintegrations = sum(['316','318','322','324','330']);
        var deductions = sum(['342','350']);
        var rfAvant = v('312') - v('314') + reintegrations - deductions;
        if (rfAvant >= 0) {
            s('352', rfAvant);
            s('354', 0);
        } else {
            s('352', 0);
            s('354', -rfAvant);
        }

        // Résultat fiscal après imputation
        var rfApres = (v('352') - v('354')) - v('356') - v('360') + v('366') - v('368');
        if (rfApres >= 0) {
            s('370', rfApres);
            s('372', 0);
        } else {
            s('370', 0);
            s('372', -rfApres);
        }
    }

    // Mode lecture / édition
    var btnEdit = document.getElementById('btnEdit');
    var btnSave = document.getElementById('btnSave');

    compute();

    var allFields = Array.prototype.slice.call(form.querySelectorAll('.cr-field'));

    window._crEdit = function() {
        for (var i = 0; i < allFields.length; i++) {
            allFields[i].removeAttribute('readonly');
            allFields[i].removeAttribute('tabindex');
        }
        btnEdit.classList.add('hidden');
        btnSave.classList.remove('hidden');
    };

    var btnReset = document.getElementById('btnReset');
    var resetActivated = false;

    form.addEventListener('input', function(e) {
        compute();
        if (!resetActivated && e.target && e.target.classList.contains('cr-field')) {
            resetActivated = true;
            btnReset.classList.remove('pointer-events-none', 'text-slate-400', 'border-slate-300');
            btnReset.classList.add('text-amber-700', 'border-amber-300', 'bg-amber-50', 'hover:bg-amber-100', 'cursor-pointer');
        }
    });
});
