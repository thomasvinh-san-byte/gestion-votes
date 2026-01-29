// /assets/js/tally.js - VERSION CORRIGÉE (Pour = Total dynamique)

let manualTotalMode = false; // true = total fixé manuellement, false = total = somme

// ---------- Tally (comptage manuel) ----------

async function loadCurrentMotionTally() {
    if (!state.currentMotionId) {
        state.tally = {
            manual_total: 0,
            manual_against: 0,
            manual_abstain: 0,
            manual_for: 0,
        };
        manualTotalMode = false;
        setTallyInputsFromState();
        return;
    }

    const { status, body } = await api("/api/v1/motion_tally.php?motion_id=" + encodeURIComponent(state.currentMotionId));

    state.tally = {
        manual_total: 0,
        manual_against: 0,
        manual_abstain: 0,
        manual_for: 0,
    };
    manualTotalMode = false;

    if (body?.ok) {
        const data = body.body || body.data || body;

        state.tally = {
            manual_total:   Number(data.manual_total)   || 0,
            manual_against: Number(data.manual_against) || 0,
            manual_abstain: Number(data.manual_abstain) || 0,
            manual_for:     Number(data.manual_for)     || 0,
        };
        
        // Si un total est enregistré, on passe en mode manuel
        if (state.tally.manual_total > 0) {
            manualTotalMode = true;
        }
    }
    
    setTallyInputsFromState();
}

function setTallyInputsFromState() {
    const t = state.tally;
    const totalInput   = document.getElementById('total_voters');
    const againstInput = document.getElementById('against_votes');
    const abstainInput = document.getElementById('abstention_votes');
    const forInput     = document.getElementById('for_votes');

    if (!totalInput || !againstInput || !abstainInput || !forInput) return;

    totalInput.value   = t.manual_total;
    againstInput.value = t.manual_against;
    abstainInput.value = t.manual_abstain;
    
    // Toujours calculer "Pour" = Total - (Contre + Abstention)
    const calculatedFor = Math.max(0, t.manual_total - (t.manual_against + t.manual_abstain));
    forInput.value = calculatedFor;
    state.tally.manual_for = calculatedFor;
    
    updateMajorityDisplay();
}

function updateTallyDisplay() {
    const tallyBlock = document.getElementById("tally_block");
    const tallyForm  = document.getElementById("tally_form");
    const resultDiv  = document.getElementById('tally_result');
    const currentMotion = state.currentMotion;

    if (!tallyBlock || !tallyForm) return;

    if (currentMotion && motionState(currentMotion) === 'open') {
        tallyBlock.innerHTML = `<p><strong>${escapeHtml(currentMotion.motion_title)}</strong></p>`;
        tallyForm.classList.add('active');
        setTallyInputsFromState();
        if (resultDiv) resultDiv.style.display = 'block';
    } else {
        tallyBlock.innerHTML = '<p class="muted">Aucune résolution ouverte actuellement.</p>';
        tallyForm.classList.remove('active');
        if (resultDiv) resultDiv.style.display = 'none';
    }
}

/**
 * NOUVELLE LOGIQUE SIMPLIFIÉE :
 * Règles :
 * 1. Total ne bouge QUE si l'utilisateur le modifie (manualTotalMode = true)
 * 2. Si Total est fixé manuellement, "Pour" = Total - (Contre + Abstention) automatiquement
 * 3. Si Total n'est pas fixé manuellement, Total = somme automatique des 3 champs
 * 4. Contre et Abstention sont toujours éditables librement
 */
function onVoteInputChange(source) {
    console.log('onVoteInputChange:', source, 'manualTotalMode:', manualTotalMode);
    
    const totalEl      = document.getElementById('total_voters');
    const forEl        = document.getElementById('for_votes');
    const againstEl    = document.getElementById('against_votes');
    const abstentionEl = document.getElementById('abstention_votes');

    if (!totalEl || !forEl || !againstEl || !abstentionEl) return;

    let total      = parseInt(totalEl.value || "0", 10);
    let forVotes   = parseInt(forEl.value || "0", 10);
    let against    = parseInt(againstEl.value || "0", 10);
    let abstention = parseInt(abstentionEl.value || "0", 10);

    // Normalisation
    if (Number.isNaN(total)) total = 0;
    if (Number.isNaN(forVotes)) forVotes = 0;
    if (Number.isNaN(against)) against = 0;
    if (Number.isNaN(abstention)) abstention = 0;

    if (total < 0) total = 0;
    if (forVotes < 0) forVotes = 0;
    if (against < 0) against = 0;
    if (abstention < 0) abstention = 0;

    // Logique selon la source
    if (source === 'total') {
        // L'utilisateur a modifié le Total manuellement
        manualTotalMode = true;
        
        // "Pour" = Total - (Contre + Abstention)
        forVotes = Math.max(0, total - (against + abstention));
    } 
    else if (source === 'for') {
        // L'utilisateur essaie de modifier "Pour" manuellement
        if (manualTotalMode) {
            // En mode manuel, on ne permet PAS de modifier "Pour" directement
            // On le recalcule automatiquement
            forVotes = Math.max(0, total - (against + abstention));
            
            // Avertissement
            setNotif('info', 'En mode "Total fixé", le champ "Pour" est calculé automatiquement. Modifiez "Contre" ou "Abstention" pour ajuster.');
        } else {
            // En mode automatique, Total = somme
            total = forVotes + against + abstention;
        }
    }
    else if (source === 'against' || source === 'abstention') {
        // L'utilisateur a modifié Contre ou Abstention
        if (manualTotalMode) {
            // En mode manuel : "Pour" = Total - (Contre + Abstention)
            forVotes = Math.max(0, total - (against + abstention));
            
            // Si Contre + Abstention > Total, ajuster le champ modifié
            const sum = against + abstention;
            if (sum > total) {
                if (source === 'against') {
                    against = Math.max(0, total - abstention);
                } else {
                    abstention = Math.max(0, total - against);
                }
                // Recalculer "Pour" après ajustement
                forVotes = Math.max(0, total - (against + abstention));
            }
        } else {
            // En mode automatique : Total = somme
            total = forVotes + against + abstention;
        }
    }

    // Mettre à jour les champs UI
    totalEl.value = String(total);
    forEl.value = String(forVotes);
    againstEl.value = String(against);
    abstentionEl.value = String(abstention);

    // Mettre à jour le state
    state.tally.manual_total = total;
    state.tally.manual_for = forVotes;
    state.tally.manual_against = against;
    state.tally.manual_abstain = abstention;

    // Mettre à jour l'affichage
    updateMajorityDisplay();
    setNotif('', ''); // Effacer les notifications précédentes
}

/**
 * Unanimité : tous les votants votent "Pour"
 * Contre = 0, Abstention = 0, Pour = Total
 */
function setUnanimity() {
    const totalEl      = document.getElementById('total_voters');
    const forEl        = document.getElementById('for_votes');
    const againstEl    = document.getElementById('against_votes');
    const abstainEl    = document.getElementById('abstention_votes');

    if (!totalEl || !forEl || !againstEl || !abstainEl) return;

    let total = parseInt(totalEl.value || "0", 10);
    
    // Si total = 0, calculer à partir des votes actuels
    if (total === 0) {
        const forVotes   = parseInt(forEl.value || "0", 10);
        const against    = parseInt(againstEl.value || "0", 10);
        const abstention = parseInt(abstainEl.value || "0", 10);
        total = forVotes + against + abstention;
        if (total > 0) {
            totalEl.value = String(total);
            manualTotalMode = false; // On passe en mode auto
        }
    }

    if (total <= 0) {
        setNotif('error', "Le total doit être positif pour définir l'unanimité.");
        return;
    }

    // Appliquer unanimité
    againstEl.value = "0";
    abstainEl.value = "0";
    forEl.value = String(total);
    
    // Si on était en mode manuel, on y reste
    if (!manualTotalMode) {
        manualTotalMode = true; // L'unanimité fixe un total spécifique
    }

    // Mettre à jour le state et l'affichage
    state.tally.manual_total = total;
    state.tally.manual_for = total;
    state.tally.manual_against = 0;
    state.tally.manual_abstain = 0;

    updateMajorityDisplay();
    setNotif('success', 'Unanimité appliquée : tous les votants sont "Pour".');
}

function resetTally() {
    const totalEl      = document.getElementById('total_voters');
    const againstEl    = document.getElementById('against_votes');
    const abstainEl    = document.getElementById('abstention_votes');
    const forEl        = document.getElementById('for_votes');

    if (!totalEl || !againstEl || !abstainEl || !forEl) return;

    totalEl.value   = "0";
    againstEl.value = "0";
    abstainEl.value = "0";
    forEl.value     = "0";

    manualTotalMode = false;

    state.tally = {
        manual_total: 0,
        manual_against: 0,
        manual_abstain: 0,
        manual_for: 0,
    };

    setNotif('', '');
    updateMajorityDisplay();
}

function updateMajorityDisplay() {
    const total   = state.tally.manual_total;
    const forVal  = state.tally.manual_for;
    const against = state.tally.manual_against;
    const abstain = state.tally.manual_abstain;

    const sum = forVal + against + abstain;
    const majority = total > 0 ? Math.floor(total / 2) + 1 : 0;
    const majorityReached = forVal >= majority && majority > 0;

    const resultDiv = document.getElementById('tally_result');
    if (!resultDiv) return;

    let html = `<strong>Résumé du comptage (non enregistré) :</strong><br>
                Pour : ${forVal} | Contre : ${against} | Abstentions : ${abstain}<br>
                Total votants : ${total}`;

    if (manualTotalMode) {
        html += `<br><span style="color: #3b82f6;">✓ Mode "Total fixé"</span>`;
    } else {
        html += `<br><span style="color: #059669;">✓ Mode automatique (Total = somme)</span>`;
    }

    // Avertissement si incohérence
    if (manualTotalMode && sum !== total) {
        html += `<br><span style="color: #dc2626;">⚠ Attention : somme (${sum}) ≠ Total (${total})</span>`;
    }

    html += `<br>Majorité absolue : ${majority} "pour"<br>
             Résultat : <strong style="color:${majorityReached ? '#16a34a' : '#b91c1c'};">
             ${majorityReached ? 'majorité atteinte' : 'majorité non atteinte'}
             </strong>`;

    resultDiv.innerHTML = html;
    resultDiv.style.display = 'block';
}

// Fonction utilitaire pour basculer entre modes
function toggleTotalMode() {
    manualTotalMode = !manualTotalMode;
    
    const totalEl = document.getElementById('total_voters');
    const forEl = document.getElementById('for_votes');
    const againstEl = document.getElementById('against_votes');
    const abstentionEl = document.getElementById('abstention_votes');
    
    if (!totalEl || !forEl || !againstEl || !abstentionEl) return;
    
    if (manualTotalMode) {
        // Passer en mode manuel : fixer le total actuel
        const total = parseInt(totalEl.value || "0", 10);
        if (total === 0) {
            // Si total est 0, calculer à partir des votes
            const forVotes = parseInt(forEl.value || "0", 10);
            const against = parseInt(againstEl.value || "0", 10);
            const abstention = parseInt(abstentionEl.value || "0", 10);
            totalEl.value = String(forVotes + against + abstention);
        }
        setNotif('info', 'Mode "Total fixé" activé. Le champ "Pour" sera calculé automatiquement.');
    } else {
        // Passer en mode automatique : total = somme
        const forVotes = parseInt(forEl.value || "0", 10);
        const against = parseInt(againstEl.value || "0", 10);
        const abstention = parseInt(abstentionEl.value || "0", 10);
        totalEl.value = String(forVotes + against + abstention);
        setNotif('info', 'Mode automatique activé. Total = somme des votes.');
    }
    
    // Recalculer
    onVoteInputChange('total');
}

async function saveTally() {
    if (!state.currentMotionId) {
        setNotif('error', "Aucune résolution courante pour le comptage.");
        return;
    }

    const totalEl      = document.getElementById('total_voters');
    const forEl        = document.getElementById('for_votes');
    const againstEl    = document.getElementById('against_votes');
    const abstentionEl = document.getElementById('abstention_votes');

    if (!totalEl || !forEl || !againstEl || !abstentionEl) return;

    const total      = parseInt(totalEl.value || "0", 10);
    const forVotes   = parseInt(forEl.value || "0", 10);
    const against    = parseInt(againstEl.value || "0", 10);
    const abstention = parseInt(abstentionEl.value || "0", 10);

    // Validation
    if ([total, forVotes, against, abstention].some(n => Number.isNaN(n))) {
        setNotif('error', "Toutes les valeurs doivent être des nombres entiers valides.");
        return;
    }

    if (total <= 0) {
        setNotif('error', "Le nombre total de votants doit être strictement positif.");
        return;
    }

    if (forVotes < 0 || against < 0 || abstention < 0) {
        setNotif('error', "Les nombres de votes doivent être positifs.");
        return;
    }

    const sum = forVotes + against + abstention;
    
    // Validation spécifique selon le mode
    if (manualTotalMode) {
        // En mode manuel, vérifier que la somme = total
        if (sum !== total) {
            setNotif('error', `Incohérence : la somme des votes (${sum}) doit être égale au total (${total}).`);
            return;
        }
    } else {
        // En mode auto, vérifier que total = somme (devrait toujours être vrai)
        if (sum !== total) {
            setNotif('error', `Incohérence : total (${total}) ≠ somme des votes (${sum}).`);
            return;
        }
    }

    const payload = {
        motion_id: state.currentMotionId,
        manual_total: total,
        manual_for: forVotes,
        manual_against: against,
        manual_abstain: abstention
    };

    try {
        setNotif('', '');
        const res = await api("/api/v1/motion_tally.php", payload);

        if (!res.body?.ok) {
            setNotif('error', `Erreur d'enregistrement : ${res.body?.error || res.status}`);
            return;
        }

        const updated = res.body.body || res.body.data || res.body;

        if (state.motions && updated.motion_id) {
            const motionIndex = state.motions.findIndex(m => m.motion_id === updated.motion_id);
            if (motionIndex !== -1) {
                state.motions[motionIndex] = {
                    ...state.motions[motionIndex],
                    tally_status: 'done',
                    manual_total: updated.manual_total,
                    manual_for: updated.manual_for,
                    manual_against: updated.manual_against,
                    manual_abstain: updated.manual_abstain
                };
            }
        }

        setNotif('success', "Comptage enregistré avec succès.");
        renderAgendasAndMotions();
    } catch (error) {
        setNotif('error', `Erreur réseau : ${error.message}`);
    }
}

// Initialisation avec gestion améliorée des événements
document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing tally.js...');
    
    const totalInput   = document.getElementById('total_voters');
    const againstInput = document.getElementById('against_votes');
    const abstainInput = document.getElementById('abstention_votes');
    const forInput     = document.getElementById('for_votes');

    // Réinitialiser les écouteurs d'événements
    if (forInput) {
        forInput.removeEventListener('input', onVoteInputChange);
        forInput.addEventListener('input', () => {
            console.log('For input changed');
            onVoteInputChange('for');
        });
    }
    
    if (againstInput) {
        againstInput.removeEventListener('input', onVoteInputChange);
        againstInput.addEventListener('input', () => {
            console.log('Against input changed');
            onVoteInputChange('against');
        });
    }
    
    if (abstainInput) {
        abstainInput.removeEventListener('input', onVoteInputChange);
        abstainInput.addEventListener('input', () => {
            console.log('Abstention input changed');
            onVoteInputChange('abstention');
        });
    }
    
    if (totalInput) {
        totalInput.removeEventListener('input', onVoteInputChange);
        totalInput.addEventListener('input', () => {
            console.log('Total input changed');
            onVoteInputChange('total');
        });
    }

    const btnUnanimity = document.getElementById('btn_unanimity');
    if (btnUnanimity) {
        btnUnanimity.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Unanimity button clicked');
            setUnanimity();
        });
    }

    const btnReset = document.getElementById('btn_reset_tally');
    if (btnReset) {
        btnReset.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Reset button clicked');
            resetTally();
        });
    }

    const btnSave = document.getElementById('submit_vote_btn') || document.getElementById('btn_save_tally');
    if (btnSave) {
        btnSave.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Save button clicked');
            saveTally();
        });
    }
    
    // Ajouter un bouton pour basculer entre modes
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'small secondary';
    toggleBtn.id = 'toggle_total_mode';
    toggleBtn.innerHTML = '🔄 Basculer mode Total';
    toggleBtn.style.margin = '0 8px 8px 0';
    
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleTotalMode();
        // Mettre à jour le texte du bouton
        toggleBtn.innerHTML = manualTotalMode 
            ? '🔄 Mode automatique' 
            : '🔄 Mode Total fixé';
    });
    
    const tallyActions = document.querySelector('.tally-actions');
    if (tallyActions) {
        tallyActions.insertBefore(toggleBtn, tallyActions.firstChild);
    }
    
    console.log('Tally.js initialization complete');
});