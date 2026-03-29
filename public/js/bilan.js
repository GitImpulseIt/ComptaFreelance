document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('bilanForm');

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
        // ACTIF : Net = Brut - Amort pour chaque ligne
        var pairs = [
            ['010','012'], ['014','016'], ['028','030'], ['040','042'],
            ['050','052'], ['060','062'], ['064','066'], ['068','070'],
            ['072','074'], ['080','082'], ['084','086'], ['092','094']
        ];
        for (var i = 0; i < pairs.length; i++) {
            s('net_' + pairs[i][0], v(pairs[i][0]) - v(pairs[i][1]));
        }

        // Total I (actif immobilise)
        var immoBrut  = ['010','014','028','040'];
        var immoAmort = ['012','016','030','042'];
        s('044', sum(immoBrut));
        s('048', sum(immoAmort));
        s('net_044', v('044') - v('048'));

        // Total II (actif circulant)
        var circBrut  = ['050','060','064','068','072','080','084','092'];
        var circAmort = ['052','062','066','070','074','082','086','094'];
        s('096', sum(circBrut));
        s('098', sum(circAmort));
        s('net_096', v('096') - v('098'));

        // Total general actif
        s('110', v('044') + v('096'));
        s('112', v('048') + v('098'));
        s('net_110', v('110') - v('112'));

        // N-1 totaux actif
        var immoN1 = immoBrut.map(function(k) { return k + '_n1'; });
        var circN1 = circBrut.map(function(k) { return k + '_n1'; });
        s('044_n1', sum(immoN1));
        s('096_n1', sum(circN1));
        s('110_n1', v('044_n1') + v('096_n1'));

        // PASSIF : Total I (capitaux propres)
        var cp = ['120','124','126','130','132','134','136','140'];
        s('142', sum(cp));
        s('142_n1', sum(cp.map(function(k) { return k + '_n1'; })));

        // Total III (dettes)
        var dettes = ['156','164','166','172','174'];
        s('176', sum(dettes));
        s('176_n1', sum(dettes.map(function(k) { return k + '_n1'; })));

        // Total general passif
        s('180', v('142') + v('154') + v('176'));
        s('180_n1', v('142_n1') + v('154_n1') + v('176_n1'));
    }

    // Mode lecture / édition
    var btnEdit = document.getElementById('btnEdit');
    var btnSave = document.getElementById('btnSave');
    var btnReset = document.getElementById('btnReset');

    compute();

    var allFields = Array.prototype.slice.call(form.querySelectorAll('.bilan-field'));
    var resetActivated = false;

    window._bilanEdit = function() {
        for (var i = 0; i < allFields.length; i++) {
            allFields[i].removeAttribute('readonly');
            allFields[i].removeAttribute('tabindex');
        }
        btnEdit.classList.add('hidden');
        btnSave.classList.remove('hidden');
    };

    form.addEventListener('input', function(e) {
        compute();
        if (!resetActivated && e.target && e.target.classList.contains('bilan-field')) {
            resetActivated = true;
            btnReset.classList.remove('pointer-events-none', 'text-slate-400', 'border-slate-300');
            btnReset.classList.add('text-amber-700', 'border-amber-300', 'bg-amber-50', 'hover:bg-amber-100', 'cursor-pointer');
        }
    });
});
