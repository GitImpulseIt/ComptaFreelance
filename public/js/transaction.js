(function() {
    var tbody = document.getElementById('compta-tbody');
    var addBtn = document.getElementById('add-line');
    var warning = document.getElementById('sum-warning');

    // Plan comptable injecté par le template.
    var PLAN = (typeof window !== 'undefined' && Array.isArray(window.PLAN_COMPTABLE)) ? window.PLAN_COMPTABLE : [];
    var PLAN_MODE = (typeof window !== 'undefined' && window.PLAN_MODE) || 'general';

    // Ramène un taux TVA calculé au plus proche des taux légaux français
    // (corrige la dérive des centimes due aux arrondis sur facture).
    var LEGAL_VAT_RATES = [20, 13, 10, 8.5, 5.5, 2.1, 1.75, 1.05, 0.9];
    function snapToLegalRate(rate) {
        if (!rate || !isFinite(rate)) return rate;
        var nearest = LEGAL_VAT_RATES[0];
        var minDiff = Math.abs(rate - nearest);
        for (var i = 1; i < LEGAL_VAT_RATES.length; i++) {
            var d = Math.abs(rate - LEGAL_VAT_RATES[i]);
            if (d < minDiff) { minDiff = d; nearest = LEGAL_VAT_RATES[i]; }
        }
        return nearest;
    }

    // Calcul dynamique de l'équilibre débits/crédits
    function updateSum() {
        var totalDebit = 0;
        var totalCredit = 0;

        document.querySelectorAll('#compta-tbody tr.compta-row').forEach(function(row) {
            var montantInput = row.querySelector('input[name="montant_ht[]"]');
            var typeInput = row.querySelector('input[name="type[]"]');
            var tvaInput = row.querySelector('input[name="tva[]"]');

            if (montantInput && typeInput) {
                var ht = parseFloat(montantInput.value.replace(',', '.')) || 0;
                var tva = parseFloat((tvaInput ? tvaInput.value : '0').replace(',', '.')) || 0;
                var ttc = ht + tva;

                if (typeInput.value === 'DBT') {
                    totalDebit += ttc;
                } else {
                    totalCredit += ttc;
                }
            }
        });

        if (Math.abs(totalDebit - totalCredit) > 0.01) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }

    updateSum();

    // Créer une ligne d'édition
    function createInputRow() {
        var tr = document.createElement('tr');
        tr.className = 'compta-input-row bg-slate-50/50';
        tr.innerHTML =
            '<td class="px-4 py-2">' +
                '<input type="text" class="compta-input-compte w-24" placeholder="512000" autocomplete="off" />' +
                '<ul class="compte-dropdown hidden fixed z-50 w-80 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto py-1 text-sm"></ul>' +
            '</td>' +
            '<td class="px-4 py-2"><input type="text" class="compta-input-montant-ht w-28" placeholder="0,00" /></td>' +
            '<td class="px-4 py-2"><select class="compta-input-type w-24">' +
                '<option value="DBT">Débit</option>' +
                '<option value="CRD">Crédit</option>' +
            '</select></td>' +
            '<td class="px-4 py-2"><input type="text" class="compta-input-tva w-24" placeholder="0,00" /></td>' +
            '<td class="px-4 py-2"><input type="text" class="compta-input-montant-ttc w-28" placeholder="0,00" /></td>' +
            '<td class="px-4 py-2"><input type="text" class="compta-input-taux w-24" placeholder="0,00" /></td>' +
            '<td class="px-4 py-2 text-center whitespace-nowrap">' +
                '<button type="button" class="compta-ok text-emerald-600 hover:text-emerald-800 transition mr-1" title="Valider">' +
                    '<svg class="w-5 h-5 inline pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                '</button>' +
                '<button type="button" class="compta-cancel text-slate-400 hover:text-red-500 transition" title="Annuler">' +
                    '<svg class="w-5 h-5 inline pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                '</button>' +
            '</td>';
        return tr;
    }

    // Ajouter une ligne
    addBtn.addEventListener('click', function() {
        if (tbody.querySelector('.compta-input-row')) return;
        var tr = createInputRow();
        tbody.appendChild(tr);
        tr.querySelector('input').focus();
    });

    // Gestion des clics dans le tbody
    tbody.addEventListener('click', function(e) {
        var target = e.target.closest('button') || e.target;

        // Valider une ligne d'édition
        if (target.classList.contains('compta-ok')) {
            var tr = target.closest('tr');
            var compte = tr.querySelector('.compta-input-compte').value.trim();
            var montantHT = tr.querySelector('.compta-input-montant-ht').value.trim();
            var type = tr.querySelector('.compta-input-type').value;
            var tva = tr.querySelector('.compta-input-tva').value.trim();
            var montantTTC = tr.querySelector('.compta-input-montant-ttc').value.trim();
            var tauxTVA = tr.querySelector('.compta-input-taux').value.trim();

            if (compte || montantHT || tva) {
                var typeText = type === 'DBT' ? 'Débit' : 'Crédit';
                var newTr = document.createElement('tr');
                newTr.className = 'compta-row group hover:bg-slate-50/80 transition';
                newTr.innerHTML =
                    '<td class="px-4 py-3 text-sm text-slate-700">' + compte + '<input type="hidden" name="compte[]" value="' + compte + '"></td>' +
                    '<td class="px-4 py-3 text-sm text-slate-700">' + montantHT + '<input type="hidden" name="montant_ht[]" value="' + montantHT + '"></td>' +
                    '<td class="px-4 py-3 text-sm text-slate-700">' + typeText + '<input type="hidden" name="type[]" value="' + type + '"></td>' +
                    '<td class="px-4 py-3 text-sm text-slate-700">' + tva + '<input type="hidden" name="tva[]" value="' + tva + '"></td>' +
                    '<td class="px-4 py-3 text-sm text-slate-700">' + montantTTC + '</td>' +
                    '<td class="px-4 py-3 text-sm text-slate-700">' + (tauxTVA ? tauxTVA + '%' : '') + '</td>' +
                    '<td class="px-4 py-3 text-center"><span class="compta-remove text-slate-300 hover:text-red-500 cursor-pointer transition opacity-0 group-hover:opacity-100" title="Supprimer"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></span></td>';
                tbody.replaceChild(newTr, tr);
                updateSum();
            } else {
                tr.remove();
            }
        }

        // Annuler une ligne d'édition
        if (target.classList.contains('compta-cancel')) {
            target.closest('tr').remove();
        }

        // Supprimer une ligne validée
        if (target.classList.contains('compta-remove') || target.closest('.compta-remove')) {
            var tr = (target.closest('.compta-remove') || target).closest('tr');
            if (tr && !tr.hasAttribute('data-main')) {
                tr.remove();
                updateSum();
            }
        }
    });

    // Calcul automatique TVA sur la ligne d'édition
    tbody.addEventListener('input', function(e) {
        var tr = e.target.closest('.compta-input-row');
        if (!tr) return;

        var montantHT = tr.querySelector('.compta-input-montant-ht');
        var montantTVA = tr.querySelector('.compta-input-tva');
        var montantTTC = tr.querySelector('.compta-input-montant-ttc');
        var tauxTVA = tr.querySelector('.compta-input-taux');

        // Marquer le champ source
        if (e.target.classList.contains('compta-input-montant-ht')) {
            montantHT.classList.add('tva-source');
            montantTTC.classList.remove('tva-source');
        } else if (e.target.classList.contains('compta-input-montant-ttc')) {
            montantTTC.classList.add('tva-source');
            montantHT.classList.remove('tva-source');
        } else if (e.target.classList.contains('compta-input-taux')) {
            tauxTVA.classList.add('tva-source');
            montantTVA.classList.remove('tva-source');
        } else if (e.target.classList.contains('compta-input-tva')) {
            montantTVA.classList.add('tva-source');
            tauxTVA.classList.remove('tva-source');
        }

        var ht = parseFloat(montantHT.value.replace(',', '.')) || 0;
        var ttc = parseFloat(montantTTC.value.replace(',', '.')) || 0;
        var taux = parseFloat(tauxTVA.value.replace(',', '.')) || 0;
        var tva = parseFloat(montantTVA.value.replace(',', '.')) || 0;

        var montantRef = montantHT.classList.contains('tva-source') ? 'ht' : (montantTTC.classList.contains('tva-source') ? 'ttc' : null);
        var tvaRef = tauxTVA.classList.contains('tva-source') ? 'taux' : (montantTVA.classList.contains('tva-source') ? 'montant' : null);

        function fmt(v) { return v.toFixed(2).replace('.', ','); }

        if (montantRef === 'ht' && tvaRef === 'taux') {
            montantTVA.value = fmt(ht * taux / 100);
            montantTTC.value = fmt(ht * (1 + taux / 100));
        } else if (montantRef === 'ht' && tvaRef === 'montant') {
            if (ht > 0) tauxTVA.value = fmt(snapToLegalRate((tva / ht) * 100));
            montantTTC.value = fmt(ht + tva);
        } else if (montantRef === 'ttc' && tvaRef === 'taux') {
            var calcHT = ttc / (1 + taux / 100);
            montantHT.value = fmt(calcHT);
            montantTVA.value = fmt(ttc - calcHT);
        } else if (montantRef === 'ttc' && tvaRef === 'montant') {
            montantHT.value = fmt(ttc - tva);
            var calcHT2 = parseFloat(montantHT.value.replace(',', '.'));
            if (calcHT2 > 0) tauxTVA.value = fmt(snapToLegalRate((tva / calcHT2) * 100));
        } else if (montantRef === 'ht' && !tvaRef) {
            if (taux > 0) {
                montantTVA.value = fmt(ht * taux / 100);
                montantTTC.value = fmt(ht * (1 + taux / 100));
            } else if (tva > 0) {
                if (ht > 0) tauxTVA.value = fmt(snapToLegalRate((tva / ht) * 100));
                montantTTC.value = fmt(ht + tva);
            }
        } else if (montantRef === 'ttc' && !tvaRef) {
            if (taux > 0) {
                var calcHT3 = ttc / (1 + taux / 100);
                montantHT.value = fmt(calcHT3);
                montantTVA.value = fmt(ttc - calcHT3);
            } else if (tva > 0) {
                montantHT.value = fmt(ttc - tva);
                var calcHT4 = parseFloat(montantHT.value.replace(',', '.'));
                if (calcHT4 > 0) tauxTVA.value = fmt(snapToLegalRate((tva / calcHT4) * 100));
            }
        } else if (!montantRef && tvaRef === 'taux') {
            if (ht > 0) {
                montantTVA.value = fmt(ht * taux / 100);
                montantTTC.value = fmt(ht * (1 + taux / 100));
            } else if (ttc > 0) {
                var calcHT5 = ttc / (1 + taux / 100);
                montantHT.value = fmt(calcHT5);
                montantTVA.value = fmt(ttc - calcHT5);
            }
        } else if (!montantRef && tvaRef === 'montant') {
            if (ht > 0) {
                tauxTVA.value = fmt(snapToLegalRate((tva / ht) * 100));
                montantTTC.value = fmt(ht + tva);
            } else if (ttc > 0) {
                montantHT.value = fmt(ttc - tva);
                var calcHT6 = parseFloat(montantHT.value.replace(',', '.'));
                if (calcHT6 > 0) tauxTVA.value = fmt(snapToLegalRate((tva / calcHT6) * 100));
            }
        }
    });

    // --- Autocomplete du champ "Compte" sur les lignes d'édition ---
    var DROPDOWN_LIMIT = 30;

    // En mode simplifié, le plan contient un champ "sens" (D/C). On filtre
    // les suggestions selon le type de la ligne courante (DBT→D, CRD→C).
    function getSensFromInput(input) {
        if (PLAN_MODE !== 'simplifie') return null;
        var tr = input.closest('tr');
        if (!tr) return null;
        var typeSelect = tr.querySelector('.compta-input-type');
        if (!typeSelect) return null;
        return typeSelect.value === 'DBT' ? 'D' : 'C';
    }

    function filterPlan(q, sens) {
        q = (q || '').trim().toLowerCase();
        var results = [];
        for (var i = 0; i < PLAN.length && results.length < DROPDOWN_LIMIT; i++) {
            var a = PLAN[i];
            if (sens && a.sens && a.sens !== sens) continue;
            if (q && a.numero.indexOf(q) !== 0 && a.libelle.toLowerCase().indexOf(q) === -1) continue;
            results.push(a);
        }
        return results;
    }
    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function renderDropdown(dropdown, items) {
        if (!items.length) { dropdown.classList.add('hidden'); return; }
        var html = '';
        for (var i = 0; i < items.length; i++) {
            html += '<li class="compte-item px-3 py-1.5 cursor-pointer hover:bg-indigo-50' +
                    (i === 0 ? ' bg-indigo-50' : '') + '" data-numero="' + items[i].numero + '">' +
                    '<span class="font-mono text-slate-700">' + items[i].numero + '</span> ' +
                    '<span class="text-slate-500">— ' + escapeHtml(items[i].libelle) + '</span>' +
                    '</li>';
        }
        dropdown.innerHTML = html;
        dropdown.classList.remove('hidden');
    }
    function moveHighlight(dropdown, delta) {
        var items = dropdown.querySelectorAll('.compte-item');
        if (!items.length) return;
        var idx = -1;
        for (var i = 0; i < items.length; i++) {
            if (items[i].classList.contains('bg-indigo-50')) { idx = i; break; }
        }
        if (idx >= 0) items[idx].classList.remove('bg-indigo-50');
        var next = ((idx < 0 ? -1 : idx) + delta + items.length) % items.length;
        items[next].classList.add('bg-indigo-50');
        items[next].scrollIntoView({ block: 'nearest' });
    }
    function getDropdown(input) {
        return input.parentElement.querySelector('.compte-dropdown');
    }
    function positionDropdown(input, dd) {
        var rect = input.getBoundingClientRect();
        dd.style.top = (rect.bottom + 4) + 'px';
        dd.style.left = rect.left + 'px';
    }

    tbody.addEventListener('focusin', function(e) {
        if (!e.target.classList.contains('compta-input-compte')) return;
        var dd = getDropdown(e.target);
        if (!dd) return;
        positionDropdown(e.target, dd);
        renderDropdown(dd, filterPlan(e.target.value, getSensFromInput(e.target)));
    });

    tbody.addEventListener('focusout', function(e) {
        if (!e.target.classList.contains('compta-input-compte')) return;
        var dd = getDropdown(e.target);
        if (dd) setTimeout(function() { dd.classList.add('hidden'); }, 150);
    });

    tbody.addEventListener('input', function(e) {
        if (!e.target.classList.contains('compta-input-compte')) return;
        var dd = getDropdown(e.target);
        if (dd) renderDropdown(dd, filterPlan(e.target.value, getSensFromInput(e.target)));
    });

    // Si l'utilisateur change le sens (Débit/Crédit) pendant que le dropdown est ouvert,
    // re-filtrer pour refléter le nouveau sens.
    tbody.addEventListener('change', function(e) {
        if (!e.target.classList.contains('compta-input-type')) return;
        var tr = e.target.closest('tr');
        if (!tr) return;
        var input = tr.querySelector('.compta-input-compte');
        var dd = tr.querySelector('.compte-dropdown');
        if (!input || !dd || dd.classList.contains('hidden')) return;
        renderDropdown(dd, filterPlan(input.value, getSensFromInput(input)));
    });

    // mousedown plutôt que click : se déclenche avant le blur qui fermerait le dropdown
    tbody.addEventListener('mousedown', function(e) {
        var li = e.target.closest('.compte-item');
        if (!li) return;
        e.preventDefault();
        var input = li.closest('td').querySelector('.compta-input-compte');
        if (input) {
            input.value = li.dataset.numero;
            li.closest('.compte-dropdown').classList.add('hidden');
            input.focus();
        }
    });

    // Enter / Arrow / Escape dans une ligne d'édition
    tbody.addEventListener('keydown', function(e) {
        var compteInput = e.target.classList && e.target.classList.contains('compta-input-compte') ? e.target : null;
        var dd = compteInput ? getDropdown(compteInput) : null;
        var dropdownOpen = dd && !dd.classList.contains('hidden');

        if (dropdownOpen) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveHighlight(dd, 1); return; }
            if (e.key === 'ArrowUp')   { e.preventDefault(); moveHighlight(dd, -1); return; }
            if (e.key === 'Enter') {
                var active = dd.querySelector('.compte-item.bg-indigo-50');
                if (active) {
                    e.preventDefault();
                    compteInput.value = active.dataset.numero;
                    dd.classList.add('hidden');
                    return;
                }
            }
            if (e.key === 'Escape') { e.preventDefault(); dd.classList.add('hidden'); return; }
        }

        if (e.key === 'Enter') {
            var tr = e.target.closest('.compta-input-row');
            if (tr) {
                e.preventDefault();
                tr.querySelector('.compta-ok').click();
            }
        }
        if (e.key === 'Escape') {
            var tr = e.target.closest('.compta-input-row');
            if (tr) {
                e.preventDefault();
                tr.querySelector('.compta-cancel').click();
            }
        }
    });

    // --- Gestion des liens documents ---
    (function() {
        var section = document.getElementById('liens-section');
        if (!section) return;

        var transactionId = section.dataset.transactionId;
        var list = document.getElementById('liens-list');
        var addLienBtn = document.getElementById('add-lien');
        var emptyMsg = document.getElementById('liens-empty');

        function hideEmpty() {
            if (emptyMsg) emptyMsg.style.display = list.querySelectorAll('.lien-item').length ? 'none' : '';
        }

        function createLienElement(id, url) {
            var li = document.createElement('li');
            li.className = 'lien-item group flex items-center gap-2';
            li.dataset.id = id;
            li.innerHTML =
                '<svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>' +
                '<a href="' + url + '" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:text-indigo-800 truncate max-w-md" title="' + url + '">' + url + '</a>' +
                '<button type="button" class="lien-remove opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition shrink-0" data-id="' + id + '" title="Supprimer">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                '</button>';
            return li;
        }

        // Ajouter un lien
        addLienBtn.addEventListener('click', function() {
            if (list.querySelector('.lien-input-item')) return;

            var li = document.createElement('li');
            li.className = 'lien-input-item flex items-center gap-2';
            li.innerHTML =
                '<input type="text" class="lien-input flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" placeholder="https://..." />' +
                '<button type="button" class="lien-ok text-emerald-600 hover:text-emerald-800 transition" title="Valider"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>' +
                '<button type="button" class="lien-cancel text-slate-400 hover:text-red-500 transition" title="Annuler"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>';
            list.appendChild(li);
            li.querySelector('input').focus();
            if (emptyMsg) emptyMsg.style.display = 'none';
        });

        list.addEventListener('click', function(e) {
            var target = e.target.closest('button') || e.target;

            // Valider l'ajout
            if (target.classList.contains('lien-ok')) {
                var li = target.closest('li');
                var input = li.querySelector('input');
                var url = input.value.trim();
                if (!url) { li.remove(); hideEmpty(); return; }

                input.disabled = true;
                var form = new FormData();
                form.append('url', url);

                fetch('/app/banque/' + transactionId + '/liens', { method: 'POST', body: form })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var newLi = createLienElement(data.id, url);
                            list.replaceChild(newLi, li);
                        } else {
                            input.disabled = false;
                        }
                        hideEmpty();
                    });
            }

            // Annuler
            if (target.classList.contains('lien-cancel')) {
                target.closest('li').remove();
                hideEmpty();
            }

            // Supprimer un lien existant
            if (target.classList.contains('lien-remove') || target.closest('.lien-remove')) {
                var btn = target.closest('.lien-remove') || target;
                var lienId = btn.dataset.id;
                var li = btn.closest('li');

                var form = new FormData();
                form.append('lien_id', lienId);

                fetch('/app/banque/' + transactionId + '/liens/delete', { method: 'POST', body: form })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            li.remove();
                            hideEmpty();
                        }
                    });
            }
        });

        // Enter/Escape dans l'input lien
        list.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('lien-input')) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.target.closest('li').querySelector('.lien-ok').click();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.target.closest('li').querySelector('.lien-cancel').click();
                }
            }
        });
    })();
})();
