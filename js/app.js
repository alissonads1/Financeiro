// =====================================================
// APP.JS - Financeiro Pessoal (Perfis + Gastos)
// =====================================================

const App = {
    currentPage: 'dashboard',
    historyTab: 'income',
    historyPage: 1,
    user: null,

    // ---- INIT ----
    async init() {
        this.loadTheme();
        this.bindAvatarPicker();
        this.initReportYears();
        await this.checkAuth();
    },

    // ---- AUTH (Profiles) ----
    async checkAuth() {
        try {
            const data = await this.api('api/auth.php?action=check');
            if (data.authenticated && data.user) {
                this.user = data.user;
                this.showApp();
            } else {
                this.showProfileScreen();
            }
        } catch {
            this.showProfileScreen();
        }
    },

    async loadProfiles() {
        try {
            const data = await this.api('api/auth.php?action=profiles');
            const list = document.getElementById('profileList');
            if (!data.profiles || data.profiles.length === 0) {
                list.innerHTML = '<p class="text-muted">Nenhum perfil criado ainda</p>';
                return;
            }
            list.innerHTML = data.profiles.map(p => `
                <div class="profile-card" onclick="App.selectProfile(${p.id})">
                    <button class="delete-profile" onclick="event.stopPropagation(); App.deleteProfile(${p.id}, '${p.name}')" title="Excluir">✕</button>
                    <span class="avatar">${p.avatar || '👤'}</span>
                    <span class="name">${p.name}</span>
                </div>
            `).join('');
        } catch (err) {
            console.error(err);
        }
    },

    async selectProfile(id) {
        try {
            const data = await this.api('api/auth.php?action=select', 'POST', { id });

            if (data.require_pin) {
                // Show PIN modal
                this.pendingProfileId = data.id;
                document.getElementById('pinPromptName').textContent = `🔐 Senha de ${data.name}`;
                document.getElementById('loginPin').value = '';
                document.getElementById('pinLoginModal').classList.add('active');
                setTimeout(() => document.getElementById('loginPin').focus(), 100);
            } else if (data.success) {
                this.user = data.user;
                this.showApp();
            }
        } catch (err) {
            this.toast(err.message || 'Erro ao selecionar perfil', 'error');
        }
    },

    async verifyPin(e) {
        e.preventDefault();
        const pin = document.getElementById('loginPin').value.trim();
        if (!pin) return;

        try {
            const data = await this.api('api/auth.php?action=verify', 'POST', { id: this.pendingProfileId, pin });
            if (data.success) {
                this.user = data.user;
                this.closePinModal();
                this.showApp();
            }
        } catch (err) {
            this.toast('Senha incorreta', 'error');
            document.getElementById('loginPin').value = '';
            document.getElementById('loginPin').focus();
        }
    },

    closePinModal() {
        document.getElementById('pinLoginModal').classList.remove('active');
        this.pendingProfileId = null;
    },

    showCreateProfile() {
        document.getElementById('createProfileModal').classList.add('active');
        setTimeout(() => document.getElementById('profileName').focus(), 100);
    },

    closeCreateProfile() {
        document.getElementById('createProfileModal').classList.remove('active');
    },

    async createProfile(e) {
        e.preventDefault();
        const name = document.getElementById('profileName').value.trim();
        const pin = document.getElementById('profilePin').value.trim();
        const avatar = document.querySelector('.avatar-option.selected')?.dataset.avatar || '👤';

        if (!name) return this.toast('Informe o nome', 'error');

        try {
            const data = await this.api('api/auth.php?action=create', 'POST', { name, avatar, pin });
            if (data.success) {
                this.user = data.user;
                this.closeCreateProfile();
                document.getElementById('createProfileForm').reset();
                this.showApp();
                this.toast('Perfil criado!', 'success');
            }
        } catch (err) {
            this.toast(err.message || 'Erro ao criar perfil', 'error');
        }
    },

    async deleteProfile(id, name) {
        if (!confirm(`Excluir perfil "${name}" e todos os dados? Essa ação é irreversível!`)) return;
        try {
            await this.api('api/auth.php?action=delete', 'POST', { id });
            this.toast('Perfil excluído', 'success');
            this.loadProfiles();
        } catch (err) {
            this.toast(err.message || 'Erro', 'error');
        }
    },

    bindAvatarPicker() {
        document.getElementById('avatarPicker')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.avatar-option');
            if (!btn) return;
            document.querySelectorAll('.avatar-option').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
        });
    },

    showProfileScreen() {
        document.getElementById('profileScreen').style.display = '';
        document.getElementById('appContainer').style.display = 'none';
        this.loadProfiles();
    },

    showApp() {
        document.getElementById('profileScreen').style.display = 'none';
        document.getElementById('appContainer').style.display = 'flex';

        if (this.user) {
            document.getElementById('userAvatar').textContent = this.user.avatar || '👤';
            document.getElementById('userName').textContent = this.user.name;
            document.getElementById('settingsProfileInfo').textContent = `${this.user.avatar || '👤'} ${this.user.name}`;
        }

        this.navigate('dashboard');
    },

    logout() {
        this.api('api/auth.php?action=logout', 'POST').then(() => {
            this.user = null;
            this.showProfileScreen();
        });
    },

    // ---- NAVIGATION ----
    navigate(page) {
        this.currentPage = page;
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.getElementById('page-' + page)?.classList.add('active');

        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.querySelector(`.nav-item[data-page="${page}"]`)?.classList.add('active');

        const titles = {
            dashboard: 'Dashboard', income: 'Registrar Renda', expenses: 'Registrar Gasto',
            goals: 'Metas', history: 'Histórico', reports: 'Relatórios', settings: 'Configurações'
        };
        document.getElementById('pageTitle').textContent = titles[page] || page;

        // Load data
        if (page === 'dashboard') this.loadDashboard();
        else if (page === 'income') this.loadSources();
        else if (page === 'expenses') this.loadExpenseCategories();
        else if (page === 'goals') this.loadGoals();
        else if (page === 'history') this.loadHistory();
        else if (page === 'reports') this.loadReports();
        else if (page === 'settings') this.loadSettings();

        // Close sidebar on mobile
        document.getElementById('sidebar')?.classList.remove('open');
    },

    toggleSidebar() {
        document.getElementById('sidebar')?.classList.toggle('open');
    },

    // ---- THEME ----
    toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('fin_theme', next);
        document.querySelector('.theme-toggle').textContent = next === 'dark' ? '🌙' : '☀️';
        if (typeof FinCharts !== 'undefined') FinCharts.updateTheme();
    },

    loadTheme() {
        const saved = localStorage.getItem('fin_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
        document.querySelector('.theme-toggle').textContent = saved === 'dark' ? '🌙' : '☀️';
    },

    // ---- DASHBOARD ----
    async loadDashboard() {
        try {
            const data = await this.api('api/dashboard.php');

            document.getElementById('incMonth').textContent = this.currency(data.income.month);
            document.getElementById('expMonth').textContent = this.currency(data.expenses.month);
            document.getElementById('balMonth').textContent = this.currency(data.balance.month);
            document.getElementById('balTotal').textContent = this.currency(data.balance.total);

            // Evolution chart (income vs expenses)
            const allMonths = new Set();
            data.monthly_income.forEach(m => allMonths.add(m.month));
            data.monthly_expenses.forEach(m => allMonths.add(m.month));
            const sortedMonths = [...allMonths].sort();

            const incMap = {};
            data.monthly_income.forEach(m => incMap[m.month] = parseFloat(m.total));
            const expMap = {};
            data.monthly_expenses.forEach(m => expMap[m.month] = parseFloat(m.total));

            const monthLabels = sortedMonths.map(m => {
                const [y, mo] = m.split('-');
                const names = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return names[parseInt(mo) - 1] + '/' + y.slice(2);
            });

            FinCharts.renderLine('chartEvolution', monthLabels,
                [
                    { label: 'Renda', data: sortedMonths.map(m => incMap[m] || 0), color: '#10b981' },
                    { label: 'Gastos', data: sortedMonths.map(m => expMap[m] || 0), color: '#ef4444' }
                ]
            );

            // Categories (expenses) chart
            if (data.category_distribution && data.category_distribution.length > 0) {
                FinCharts.renderDoughnut('chartCategories',
                    data.category_distribution.map(c => c.category),
                    data.category_distribution.map(c => parseFloat(c.total)),
                    ['#ef4444', '#f97316', '#eab308', '#8b5cf6', '#3b82f6', '#ec4899', '#14b8a6', '#6b7280']
                );
            }

            // Sources (income) chart
            if (data.source_distribution && data.source_distribution.length > 0) {
                FinCharts.renderDoughnut('chartSources',
                    data.source_distribution.map(s => s.source),
                    data.source_distribution.map(s => parseFloat(s.total)),
                    ['#10b981', '#6366f1', '#f59e0b', '#ff6b35', '#8b5cf6', '#ec4899', '#14b8a6', '#3b82f6']
                );
            }

            // Active goals
            const goalsList = document.getElementById('dashGoalsList');
            if (data.active_goals && data.active_goals.length > 0) {
                goalsList.innerHTML = data.active_goals.map(g => `
                    <div class="dash-goal-item">
                        <span class="dg-icon">${g.icon || '🎯'}</span>
                        <div class="dg-info">
                            <div class="dg-title">${g.title}</div>
                            <div class="dg-bar"><div class="dg-fill" style="width:${g.percentage}%; background:${g.color || '#6366f1'}"></div></div>
                        </div>
                        <span class="dg-pct">${g.percentage}%</span>
                    </div>
                `).join('');
            } else {
                goalsList.innerHTML = '<p class="text-muted">Nenhuma meta ativa</p>';
            }

            // Recent income
            const recentIncList = document.getElementById('recentIncomeList');
            if (data.recent_income && data.recent_income.length > 0) {
                recentIncList.innerHTML = data.recent_income.map(r => `
                    <div class="recent-item">
                        <div class="ri-left">
                            <span class="ri-icon">${r.source_icon || '💵'}</span>
                            <div>
                                <div class="ri-name">${r.source_name || 'Renda'}</div>
                                <div class="ri-date">${this.fmtDate(r.date)}</div>
                            </div>
                        </div>
                        <span class="ri-amount income">+${this.currency(r.amount)}</span>
                    </div>
                `).join('');
            } else {
                recentIncList.innerHTML = '<p class="text-muted">Nenhum registro</p>';
            }

            // Recent expenses
            const recentExpList = document.getElementById('recentExpenseList');
            if (data.recent_expenses && data.recent_expenses.length > 0) {
                recentExpList.innerHTML = data.recent_expenses.map(r => `
                    <div class="recent-item">
                        <div class="ri-left">
                            <span class="ri-icon">${r.cat_icon || '🧾'}</span>
                            <div>
                                <div class="ri-name">${r.category_name || 'Gasto'}</div>
                                <div class="ri-date">${this.fmtDate(r.date)}</div>
                            </div>
                        </div>
                        <span class="ri-amount expense">-${this.currency(r.amount)}</span>
                    </div>
                `).join('');
            } else {
                recentExpList.innerHTML = '<p class="text-muted">Nenhum registro</p>';
            }

        } catch (err) {
            console.error('Dashboard error:', err);
        }
    },

    // ---- INCOME ----
    async loadSources() {
        try {
            const data = await this.api('api/sources.php?action=list');
            const sel = document.getElementById('incSource');
            const current = sel.value;
            sel.innerHTML = '<option value="">Selecione...</option>';
            (data.sources || []).forEach(s => {
                sel.innerHTML += `<option value="${s.id}">${s.icon} ${s.name}</option>`;
            });
            sel.value = current;
        } catch (err) {
            console.error(err);
        }
        document.getElementById('incDate').value = new Date().toISOString().split('T')[0];
    },

    async createIncome(e) {
        e.preventDefault();
        const amount = parseFloat(document.getElementById('incAmount').value);
        const date = document.getElementById('incDate').value;
        const sourceId = document.getElementById('incSource').value;
        const type = document.getElementById('incType').value;
        const observation = document.getElementById('incObs').value.trim();
        const tags = document.getElementById('incTags').value.trim();

        if (!amount || amount <= 0) return this.toast('Informe o valor', 'error');

        try {
            await this.api('api/incomes.php?action=create', 'POST', {
                amount, date, source_id: sourceId || null, type, observation, tags
            });
            this.toast('Renda registrada!', 'success');
            document.getElementById('incomeForm').reset();
            document.getElementById('incDate').value = new Date().toISOString().split('T')[0];
        } catch (err) {
            this.toast(err.message || 'Erro ao registrar', 'error');
        }
    },

    // ---- EXPENSES ----
    async loadExpenseCategories() {
        try {
            const data = await this.api('api/expenses.php?action=categories');
            const sel = document.getElementById('expCategory');
            const current = sel.value;
            sel.innerHTML = '<option value="">Selecione...</option>';
            (data.categories || []).forEach(c => {
                sel.innerHTML += `<option value="${c.id}">${c.icon} ${c.name}</option>`;
            });
            sel.value = current;
        } catch (err) {
            console.error(err);
        }
        document.getElementById('expDate').value = new Date().toISOString().split('T')[0];
    },

    async loadExpenseSummary() {
        // Default: últimos 6 meses se campos vazios
        const fromInput = document.getElementById('expReportFrom');
        const toInput = document.getElementById('expReportTo');
        if (!fromInput.value) {
            const d = new Date();
            d.setMonth(d.getMonth() - 5);
            fromInput.value = d.toISOString().split('T')[0].slice(0, 8) + '01';
        }
        if (!toInput.value) {
            toInput.value = new Date().toISOString().split('T')[0];
        }

        try {
            const data = await this.api(`api/expenses.php?action=summary&date_from=${fromInput.value}&date_to=${toInput.value}`);
            const body = document.getElementById('expReportBody');

            if (!data.months || data.months.length === 0) {
                body.innerHTML = '<p class="text-muted" style="text-align:center;padding:30px">Nenhum gasto encontrado nesse período 🤷</p>';
                document.getElementById('rptTotal').textContent = 'R$ 0,00';
                document.getElementById('rptAvgMonth').textContent = 'R$ 0,00';
                document.getElementById('rptWorstMonth').textContent = '—';
                document.getElementById('rptCount').textContent = '0';
                return;
            }

            // Preencher cards de resumo
            const total = data.period_total;
            const avgMonth = total / (data.months.length || 1);
            const worst = data.months.reduce((a, b) => b.total > a.total ? b : a, data.months[0]);
            document.getElementById('rptTotal').textContent = this.currency(total);
            document.getElementById('rptAvgMonth').textContent = this.currency(avgMonth);
            document.getElementById('rptWorstMonth').textContent = worst.label;
            document.getElementById('rptCount').textContent = data.period_count;

            // Calcular o valor máximo para as barras
            const maxTotal = Math.max(...data.months.map(m => m.total), 1);
            const now = new Date();
            const currentM = now.getMonth() + 1;
            const currentY = now.getFullYear();

            // Renderizar cards por mês
            body.innerHTML = data.months.map((m, i) => {
                const pct = Math.round(m.total / maxTotal * 100);
                const isCurrent = m.month === currentM && m.year === currentY;

                // Variação vs mês anterior
                const prev = data.months[i + 1];
                let varHtml = '';
                if (prev && prev.total > 0) {
                    const varPct = ((m.total - prev.total) / prev.total * 100).toFixed(0);
                    const varColor = varPct > 0 ? '#ef4444' : varPct < 0 ? '#22c55e' : '#9ca3af';
                    const varIcon = varPct > 0 ? '↑' : varPct < 0 ? '↓' : '=';
                    varHtml = `<span style="color:${varColor};font-weight:700;font-size:0.85rem">${varIcon} ${Math.abs(varPct)}% vs mês anterior</span>`;
                } else if (i < data.months.length - 1) {
                    varHtml = '<span style="color:#9ca3af;font-size:0.8rem">— sem comparação</span>';
                }

                const borderColor = isCurrent ? '#ef4444' : 'var(--border-color)';
                const badge = isCurrent ? '<span style="background:#ef4444;color:#fff;padding:2px 8px;border-radius:10px;font-size:0.7rem;font-weight:700;margin-left:8px">MÊS ATUAL</span>' : '';

                return `<div style="padding:16px;background:var(--bg-card);border-radius:var(--radius);border-left:4px solid ${borderColor};margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="font-size:1.2rem">${isCurrent ? '📅' : '📆'}</span>
                            <span style="font-weight:700;font-size:1.05rem">${m.label}</span>
                            ${badge}
                        </div>
                        <span style="font-weight:800;font-size:1.25rem;color:#ef4444">${this.currency(m.total)}</span>
                    </div>
                    <div style="background:var(--bg-input);border-radius:6px;height:10px;margin-bottom:10px;overflow:hidden">
                        <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#ef4444,#f97316);border-radius:6px;transition:width 0.5s"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                        <div style="display:flex;gap:16px;flex-wrap:wrap">
                            <span class="text-muted" style="font-size:0.85rem">🧾 ${m.count} registro${m.count !== 1 ? 's' : ''}</span>
                            <span class="text-muted" style="font-size:0.85rem">📊 ${this.currency(m.avg_day)}/dia</span>
                        </div>
                        ${varHtml}
                    </div>
                </div>`;
            }).join('');

            // Gráfico de barras
            const reversed = [...data.months].reverse();
            FinCharts.renderBar('chartExpMonthly',
                reversed.map(m => m.label),
                [{ label: 'Gastos', data: reversed.map(m => m.total), color: '#ef4444' }]
            );

            // Guardar dados e mostrar botão PDF
            this._reportData = data;
            document.getElementById('btnDownloadPDF').style.display = 'inline-flex';

        } catch (err) {
            console.error(err);
        }
    },

    async downloadExpenseReportPDF() {
        const fromDate = document.getElementById('expReportFrom').value;
        const toDate = document.getElementById('expReportTo').value;
        if (!fromDate || !toDate) return this.toast('Selecione as datas primeiro', 'error');

        try {
            // Buscar todos os gastos do período
            const data = await this.api(`api/expenses.php?action=list&date_from=${fromDate}&date_to=${toDate}&limit=100&order=ASC`);
            const records = data.records || [];

            if (records.length === 0) return this.toast('Nenhum gasto nesse período', 'error');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Cabeçalho
            doc.setFillColor(99, 102, 241);
            doc.rect(0, 0, 210, 30, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('Meus Gastos', 14, 15);
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text(`${this.fmtDate(fromDate)} até ${this.fmtDate(toDate)}`, 14, 24);
            doc.text(new Date().toLocaleDateString('pt-BR'), 196, 24, { align: 'right' });

            // Tabela de gastos
            const tableData = records.map(r => [
                this.fmtDate(r.date),
                r.cat_label || r.category_name || '—',
                r.observation || '—',
                this.currency(parseFloat(r.amount))
            ]);

            const totalGasto = records.reduce((s, r) => s + parseFloat(r.amount), 0);

            doc.autoTable({
                startY: 38,
                head: [['Data', 'Categoria', 'Descrição', 'Valor']],
                body: tableData,
                foot: [['', '', 'Total', this.currency(totalGasto)]],
                theme: 'striped',
                headStyles: { fillColor: [99, 102, 241], textColor: [255, 255, 255], fontStyle: 'bold' },
                footStyles: { fillColor: [240, 240, 245], textColor: [30, 30, 50], fontStyle: 'bold', halign: 'right' },
                bodyStyles: { fontSize: 9 },
                columnStyles: {
                    0: { cellWidth: 28 },
                    1: { cellWidth: 35 },
                    2: { cellWidth: 'auto' },
                    3: { cellWidth: 32, halign: 'right', fontStyle: 'bold' }
                },
                margin: { left: 14, right: 14 }
            });

            // Rodapé
            const pageH = doc.internal.pageSize.height;
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 160);
            doc.text(`${records.length} registro${records.length !== 1 ? 's' : ''} • Financeiro Pessoal`, 105, pageH - 10, { align: 'center' });

            doc.save(`gastos-${fromDate}-a-${toDate}.pdf`);
            this.toast('PDF baixado!', 'success');
        } catch (err) {
            console.error(err);
            this.toast('Erro ao gerar PDF', 'error');
        }
    },

    async createExpense(e) {
        e.preventDefault();
        const amount = parseFloat(document.getElementById('expAmount').value);
        const date = document.getElementById('expDate').value;
        const categoryId = document.getElementById('expCategory').value;
        const observation = document.getElementById('expObs').value.trim();
        const tags = document.getElementById('expTags').value.trim();

        if (!amount || amount <= 0) return this.toast('Informe o valor', 'error');

        try {
            await this.api('api/expenses.php?action=create', 'POST', {
                amount, date, category_id: categoryId || null, observation, tags
            });
            this.toast('Gasto registrado!', 'success');
            document.getElementById('expenseForm').reset();
            document.getElementById('expDate').value = new Date().toISOString().split('T')[0];
        } catch (err) {
            this.toast(err.message || 'Erro ao registrar', 'error');
        }
    },

    // ---- GOALS ----
    async loadGoals() {
        try {
            const data = await this.api('api/goals.php?action=list');
            const container = document.getElementById('goalsList');
            if (!data.goals || data.goals.length === 0) {
                container.innerHTML = '<p class="text-muted" style="grid-column:1/-1;text-align:center;padding:40px">Nenhuma meta criada. Clique em "+ Nova Meta" para começar!</p>';
                return;
            }
            container.innerHTML = data.goals.map(g => {
                const pct = g.target_amount > 0 ? Math.min(100, (g.current_amount / g.target_amount * 100)).toFixed(1) : 0;
                return `
                    <div class="goal-card">
                        <div class="goal-card-header">
                            <div>
                                <span class="goal-icon">${g.icon || '🎯'}</span>
                                <div class="goal-title">${g.title}</div>
                            </div>
                            <span class="goal-pct" style="color:${g.color}">${pct}%</span>
                        </div>
                        <div class="goal-amounts">
                            <span class="goal-current">${this.currency(g.current_amount)}</span>
                            <span class="goal-target">de ${this.currency(g.target_amount)}</span>
                        </div>
                        <div class="goal-progress-bar"><div class="bar-fill" style="width:${pct}%; background:${g.color}"></div></div>
                        <div class="goal-deposit-form">
                            <button class="btn btn-outline btn-sm" onclick="App.depositGoal(${g.id}, -1)" title="Sacar/Corrigir">-</button>
                            <input type="number" step="0.01" min="0.01" placeholder="R$ valor" id="deposit-${g.id}">
                            <button class="btn btn-success btn-sm" onclick="App.depositGoal(${g.id}, 1)">+ Depositar</button>
                        </div>
                        <div style="margin-top:8px"><span class="goal-status-badge ${g.status}">${g.status === 'active' ? '🟢 Ativa' : g.status === 'completed' ? '🏆 Concluída' : '⏸️ Pausada'}</span></div>
                    </div>`;
            }).join('');
        } catch (err) {
            console.error(err);
        }
    },

    showGoalModal() { document.getElementById('goalModal').classList.add('active'); },
    closeGoalModal() { document.getElementById('goalModal').classList.remove('active'); },

    async createGoal(e) {
        e.preventDefault();
        const title = document.getElementById('goalTitle').value.trim();
        const targetAmount = parseFloat(document.getElementById('goalTarget').value);
        const deadline = document.getElementById('goalDeadline').value;
        const icon = document.getElementById('goalIcon').value;
        const color = document.getElementById('goalColor').value;

        if (!title) return this.toast('Informe o título', 'error');
        if (!targetAmount || targetAmount <= 0) return this.toast('Informe o valor', 'error');

        try {
            await this.api('api/goals.php?action=create', 'POST', {
                title, target_amount: targetAmount, deadline: deadline || null, icon, color
            });
            this.toast('Meta criada!', 'success');
            this.closeGoalModal();
            document.getElementById('createGoalForm').reset();
            this.loadGoals();
        } catch (err) {
            this.toast(err.message || 'Erro', 'error');
        }
    },

    async depositGoal(id, multiplier = 1) {
        const input = document.getElementById('deposit-' + id);
        let amount = parseFloat(input?.value);
        if (!amount || amount <= 0) return this.toast('Informe um valor', 'error');
        amount = amount * multiplier;

        try {
            await this.api('api/goals.php?action=deposit', 'POST', { id, amount });
            this.toast(amount > 0 ? 'Depósito realizado!' : 'Valor atualizado!', 'success');
            this.loadGoals();
        } catch (err) {
            this.toast(err.message || 'Erro', 'error');
        }
    },

    // ---- HISTORY ----
    switchHistoryTab(tab) {
        this.historyTab = tab;
        this.historyPage = 1;
        document.querySelectorAll('.tab-bar .tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`.tab-btn[onclick*="'${tab}'"]`)?.classList.add('active');

        const historyContent = document.getElementById('historyContent');
        const reportContent = document.getElementById('reportContent');

        if (tab === 'report') {
            historyContent.style.display = 'none';
            reportContent.style.display = 'block';
            this.loadExpenseSummary();
        } else {
            historyContent.style.display = 'block';
            reportContent.style.display = 'none';
            this.loadHistoryFilters();
            this.loadHistory();
        }
    },

    async loadHistoryFilters() {
        const filterSel = document.getElementById('histFilter');
        filterSel.innerHTML = '<option value="">Todas</option>';
        try {
            if (this.historyTab === 'income') {
                const data = await this.api('api/sources.php?action=list');
                (data.sources || []).forEach(s => {
                    filterSel.innerHTML += `<option value="${s.id}">${s.icon} ${s.name}</option>`;
                });
            } else {
                const data = await this.api('api/expenses.php?action=categories');
                (data.categories || []).forEach(c => {
                    filterSel.innerHTML += `<option value="${c.id}">${c.icon} ${c.name}</option>`;
                });
            }
        } catch (err) {
            console.error(err);
        }
    },

    async loadHistory() {
        const search = document.getElementById('histSearch')?.value || '';
        const from = document.getElementById('histFrom')?.value || '';
        const to = document.getElementById('histTo')?.value || '';
        const filterId = document.getElementById('histFilter')?.value || '';

        let url, filterParam;
        if (this.historyTab === 'income') {
            filterParam = filterId ? `&source_id=${filterId}` : '';
            url = `api/incomes.php?action=list&page=${this.historyPage}&search=${encodeURIComponent(search)}&date_from=${from}&date_to=${to}${filterParam}`;
        } else {
            filterParam = filterId ? `&category_id=${filterId}` : '';
            url = `api/expenses.php?action=list&page=${this.historyPage}&search=${encodeURIComponent(search)}&date_from=${from}&date_to=${to}${filterParam}`;
        }

        try {
            const data = await this.api(url);

            // Table head
            const head = document.getElementById('historyHead');
            if (this.historyTab === 'income') {
                head.innerHTML = '<th>Data</th><th>Valor</th><th>Fonte</th><th>Tipo</th><th>Obs</th><th>Ação</th>';
            } else {
                head.innerHTML = '<th>Data</th><th>Valor</th><th>Categoria</th><th>Obs</th><th>Ação</th>';
            }

            // Table body
            const body = document.getElementById('historyBody');
            if (!data.records || data.records.length === 0) {
                const cols = this.historyTab === 'income' ? 6 : 5;
                body.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:32px;color:var(--text-muted)">Nenhum registro encontrado</td></tr>`;
            } else {
                body.innerHTML = data.records.map(r => {
                    if (this.historyTab === 'income') {
                        return `<tr>
                            <td>${this.fmtDate(r.date)}</td>
                            <td class="amount-cell income">+${this.currency(r.amount)}</td>
                            <td>${(r.source_icon || '💵') + ' ' + (r.source_name || r.source_label || 'Outros')}</td>
                            <td>${r.type || '-'}</td>
                            <td>${r.observation || '-'}</td>
                            <td><button class="btn btn-outline btn-sm" onclick="App.deleteIncome(${r.id})" title="Excluir">🗑️</button></td>
                        </tr>`;
                    } else {
                        return `<tr>
                            <td>${this.fmtDate(r.date)}</td>
                            <td class="amount-cell expense">-${this.currency(r.amount)}</td>
                            <td>${(r.cat_icon || '🧾') + ' ' + (r.category_name || r.cat_label || 'Outros')}</td>
                            <td>${r.observation || '-'}</td>
                            <td><button class="btn btn-outline btn-sm" onclick="App.deleteExpense(${r.id})" title="Excluir">🗑️</button></td>
                        </tr>`;
                    }
                }).join('');
            }

            // Pagination
            const pagDiv = document.getElementById('historyPagination');
            if (data.pages > 1) {
                let html = '';
                for (let i = 1; i <= data.pages; i++) {
                    html += `<button class="${i === data.page ? 'active' : ''}" onclick="App.historyPage=${i}; App.loadHistory()">${i}</button>`;
                }
                pagDiv.innerHTML = html;
            } else {
                pagDiv.innerHTML = '';
            }
        } catch (err) {
            console.error(err);
        }
    },

    async deleteIncome(id) {
        if (!confirm('Excluir este registro?')) return;
        try {
            await this.api('api/incomes.php?action=delete', 'POST', { id });
            this.toast('Registro excluído', 'success');
            this.loadHistory();
        } catch (err) {
            this.toast(err.message || 'Erro', 'error');
        }
    },

    async deleteExpense(id) {
        if (!confirm('Excluir este gasto?')) return;
        try {
            await this.api('api/expenses.php?action=delete', 'POST', { id });
            this.toast('Gasto excluído', 'success');
            this.loadHistory();
        } catch (err) {
            this.toast(err.message || 'Erro', 'error');
        }
    },

    exportHistory() {
        const from = document.getElementById('histFrom')?.value || '';
        const to = document.getElementById('histTo')?.value || '';
        const filterId = document.getElementById('histFilter')?.value || '';

        let url;
        if (this.historyTab === 'income') {
            url = `api/incomes.php?action=export&date_from=${from}&date_to=${to}${filterId ? '&source_id=' + filterId : ''}`;
        } else {
            url = `api/expenses.php?action=export&date_from=${from}&date_to=${to}${filterId ? '&category_id=' + filterId : ''}`;
        }
        window.location.href = url;
    },

    // ---- REPORTS ----
    initReportYears() {
        const sel = document.getElementById('reportYear');
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= currentYear - 5; y--) {
            sel.innerHTML += `<option value="${y}">${y}</option>`;
        }
        document.getElementById('reportMonth').value = new Date().getMonth() + 1;
    },

    async loadReports() {
        const type = document.getElementById('reportType').value;
        const month = document.getElementById('reportMonth').value;
        const year = document.getElementById('reportYear').value;

        const monthSel = document.getElementById('reportMonth');
        monthSel.style.display = type === 'annual' ? 'none' : '';

        try {
            const url = type === 'monthly'
                ? `api/reports.php?action=monthly&month=${month}&year=${year}`
                : `api/reports.php?action=annual&year=${year}`;

            const data = await this.api(url);
            const summary = document.getElementById('reportSummary');
            const balance = (data.income_total || 0) - (data.expense_total || 0);

            summary.innerHTML = `
                <div class="report-stat"><span class="rs-value income">${this.currency(data.income_total)}</span><span class="rs-label">Total Renda</span></div>
                <div class="report-stat"><span class="rs-value expense">${this.currency(data.expense_total)}</span><span class="rs-label">Total Gastos</span></div>
                <div class="report-stat"><span class="rs-value balance">${this.currency(balance)}</span><span class="rs-label">Saldo</span></div>
                <div class="report-stat"><span class="rs-value">${data.income_count || 0}</span><span class="rs-label">Registros Renda</span></div>
                <div class="report-stat"><span class="rs-value">${data.expense_count || 0}</span><span class="rs-label">Registros Gastos</span></div>
                ${data.income_growth !== undefined ? `<div class="report-stat"><span class="rs-value" style="color:${data.income_growth >= 0 ? 'var(--success)' : 'var(--danger)'}">${data.income_growth >= 0 ? '+' : ''}${data.income_growth}%</span><span class="rs-label">Crescimento</span></div>` : ''}
            `;

            // Daily/Monthly chart
            if (type === 'monthly') {
                const incLabels = (data.daily_income || []).map(d => this.fmtDate(d.date));
                const incData = (data.daily_income || []).map(d => parseFloat(d.total));
                const expLabels = (data.daily_expenses || []).map(d => this.fmtDate(d.date));
                const expData = (data.daily_expenses || []).map(d => parseFloat(d.total));

                const allLabels = [...new Set([...incLabels, ...expLabels])].sort();
                const incMap = {}; data.daily_income?.forEach(d => incMap[this.fmtDate(d.date)] = parseFloat(d.total));
                const expMap = {}; data.daily_expenses?.forEach(d => expMap[this.fmtDate(d.date)] = parseFloat(d.total));

                FinCharts.renderLine('chartReportDaily', allLabels, [
                    { label: 'Renda', data: allLabels.map(l => incMap[l] || 0), color: '#10b981' },
                    { label: 'Gastos', data: allLabels.map(l => expMap[l] || 0), color: '#ef4444' }
                ]);
            } else {
                const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                const incMap = {}; (data.monthly_income || []).forEach(m => incMap[m.month] = parseFloat(m.total));
                const expMap = {}; (data.monthly_expenses || []).forEach(m => expMap[m.month] = parseFloat(m.total));

                FinCharts.renderLine('chartReportDaily', months, [
                    { label: 'Renda', data: months.map((_, i) => incMap[i + 1] || 0), color: '#10b981' },
                    { label: 'Gastos', data: months.map((_, i) => expMap[i + 1] || 0), color: '#ef4444' }
                ]);
            }

            // Comparison chart (income by source vs expenses by category)
            const compLabels = [];
            const compInc = [];
            const compExp = [];

            (data.by_source || []).forEach(s => { compLabels.push(s.name); compInc.push(parseFloat(s.total)); compExp.push(0); });
            (data.by_category || []).forEach(c => { compLabels.push(c.name); compInc.push(0); compExp.push(parseFloat(c.total)); });

            if (compLabels.length > 0) {
                FinCharts.renderBar('chartReportComparison', compLabels, [
                    { label: 'Renda', data: compInc, color: '#10b981' },
                    { label: 'Gastos', data: compExp, color: '#ef4444' }
                ]);
            }
        } catch (err) {
            console.error(err);
        }
    },

    // ---- SETTINGS ----
    async loadSettings() {
        // Sources
        try {
            const data = await this.api('api/sources.php?action=list');
            const list = document.getElementById('sourcesList');
            list.innerHTML = (data.sources || []).map(s => `
                <div class="settings-item" id="source-${s.id}">
                    <span class="si-label" onclick='App.editSource(${s.id}, ${JSON.stringify(s.name).replace(/'/g, "&#39;")}, ${JSON.stringify(s.icon || "").replace(/'/g, "&#39;")})' title="Clique para editar">
                        ${s.icon} <span class="si-name">${s.name}</span> ✏️
                    </span>
                    <button class="si-delete" onclick="App.deleteSource(${s.id})">✕</button>
                </div>
            `).join('');
        } catch (err) { console.error(err); }

        // Categories
        try {
            const data = await this.api('api/expenses.php?action=categories');
            const list = document.getElementById('categoriesList');
            list.innerHTML = (data.categories || []).map(c => `
                <div class="settings-item" id="cat-${c.id}">
                    <span class="si-label" onclick='App.editCategory(${c.id}, ${JSON.stringify(c.name).replace(/'/g, "&#39;")}, ${JSON.stringify(c.icon || "").replace(/'/g, "&#39;")})' title="Clique para editar">
                        ${c.icon} <span class="si-name">${c.name}</span> ✏️
                    </span>
                    <button class="si-delete" onclick="App.deleteCategory(${c.id})">✕</button>
                </div>
            `).join('');
        } catch (err) { console.error(err); }
    },

    editSource(id, name, icon) {
        const el = document.getElementById(`source-${id}`);
        el.innerHTML = `
            <div style="display:flex;gap:6px;align-items:center;flex:1">
                <input type="text" id="editSourceIcon-${id}" value="${icon}" style="width:40px;padding:6px;text-align:center;background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-xs);font-size:1.1rem;color:var(--text-primary)">
                <input type="text" id="editSourceName-${id}" value="${name}" style="flex:1;padding:6px 10px;background:var(--bg-input);border:1px solid var(--accent);border-radius:var(--radius-xs);color:var(--text-primary);font-family:var(--font);outline:none" autofocus>
            </div>
            <div style="display:flex;gap:4px">
                <button class="btn btn-primary" style="padding:4px 10px;font-size:0.8rem" onclick="App.saveSource(${id})">✓</button>
                <button class="btn btn-outline" style="padding:4px 10px;font-size:0.8rem" onclick="App.loadSettings()">✕</button>
            </div>
        `;
        const input = document.getElementById(`editSourceName-${id}`);
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') App.saveSource(id); if (e.key === 'Escape') App.loadSettings(); });
    },

    async saveSource(id) {
        const name = document.getElementById(`editSourceName-${id}`).value.trim();
        const icon = document.getElementById(`editSourceIcon-${id}`).value.trim();
        if (!name) return this.toast('Nome não pode ficar vazio', 'error');
        try {
            await this.api('api/sources.php?action=update', 'POST', { id, name, icon });
            this.toast('Fonte atualizada!', 'success');
            this.loadSettings();
            this.loadSources();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    editCategory(id, name, icon) {
        const el = document.getElementById(`cat-${id}`);
        el.innerHTML = `
            <div style="display:flex;gap:6px;align-items:center;flex:1">
                <input type="text" id="editCatIcon-${id}" value="${icon}" style="width:40px;padding:6px;text-align:center;background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-xs);font-size:1.1rem;color:var(--text-primary)">
                <input type="text" id="editCatName-${id}" value="${name}" style="flex:1;padding:6px 10px;background:var(--bg-input);border:1px solid var(--accent);border-radius:var(--radius-xs);color:var(--text-primary);font-family:var(--font);outline:none" autofocus>
            </div>
            <div style="display:flex;gap:4px">
                <button class="btn btn-primary" style="padding:4px 10px;font-size:0.8rem" onclick="App.saveCategory(${id})">✓</button>
                <button class="btn btn-outline" style="padding:4px 10px;font-size:0.8rem" onclick="App.loadSettings()">✕</button>
            </div>
        `;
        const input = document.getElementById(`editCatName-${id}`);
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') App.saveCategory(id); if (e.key === 'Escape') App.loadSettings(); });
    },

    async saveCategory(id) {
        const name = document.getElementById(`editCatName-${id}`).value.trim();
        const icon = document.getElementById(`editCatIcon-${id}`).value.trim();
        if (!name) return this.toast('Nome não pode ficar vazio', 'error');
        try {
            await this.api('api/expenses.php?action=update_category', 'POST', { id, name, icon });
            this.toast('Categoria atualizada!', 'success');
            this.loadSettings();
            this.loadExpenseCategories();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    async addSource() {
        const name = document.getElementById('newSourceName').value.trim();
        if (!name) return;
        try {
            await this.api('api/sources.php?action=create', 'POST', { name });
            document.getElementById('newSourceName').value = '';
            this.toast('Fonte adicionada!', 'success');
            this.loadSettings();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    async deleteSource(id) {
        if (!confirm('Excluir esta fonte?')) return;
        try {
            await this.api('api/sources.php?action=delete', 'POST', { id });
            this.toast('Fonte excluída', 'success');
            this.loadSettings();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    async addCategory() {
        const name = document.getElementById('newCategoryName').value.trim();
        if (!name) return;
        try {
            await this.api('api/expenses.php?action=add_category', 'POST', { name });
            document.getElementById('newCategoryName').value = '';
            this.toast('Categoria adicionada!', 'success');
            this.loadSettings();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    async deleteCategory(id) {
        if (!confirm('Excluir esta categoria?')) return;
        try {
            await this.api('api/expenses.php?action=delete_category', 'POST', { id });
            this.toast('Categoria excluída', 'success');
            this.loadSettings();
        } catch (err) { this.toast(err.message || 'Erro', 'error'); }
    },

    // ---- UTILITIES ----
    async api(url, method = 'GET', body = null) {
        const opts = { method, headers: { 'Content-Type': 'application/json' } };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('text/csv')) return;
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Erro na requisição');
        return data;
    },

    currency(value) {
        return parseFloat(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    },

    fmtDate(dateStr) {
        if (!dateStr) return '-';
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    },

    toast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    },

    // PWA Installation
    deferredPrompt: null,
    installPWA() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.then((result) => {
                if (result.outcome === 'accepted') {
                    console.log('User accepted install');
                }
                this.deferredPrompt = null;
            });
        } else {
            alert('Para instalar o App:\n\n1. Abra o menu do navegador (⋮ ou ⬆️)\n2. Toque em "Adicionar à Tela Inicial" ou "Instalar Aplicativo"');
        }
    }
};

// PWA Install Event Listener
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    App.deferredPrompt = e;
});

// Init
document.addEventListener('DOMContentLoaded', () => App.init());
