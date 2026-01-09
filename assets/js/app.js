const TableManager = {
    renderDashboardTable(sites) {
        const tbody = document.getElementById('sites-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        sites.forEach(site => {
            const tr = document.createElement('tr');
            tr.className = 'cursor-pointer';
            tr.onclick = () => this.showDetails(site.id);

            const u = site.updates || { core: 0, plugins: 0, themes: 0 };
            const total = (parseInt(u.core) || 0) + (parseInt(u.plugins) || 0) + (parseInt(u.themes) || 0);
            
            let updateHTML = total > 0 
                ? `<span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                    <i class="ph ph-arrow-fat-up"></i> 
                    ${u.core > 0 ? 'Core ' : ''} ${u.plugins > 0 ? 'P: '+u.plugins : ''} ${u.themes > 0 ? ' T: '+u.themes : ''}
                   </span>`
                : '<span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Aktuell</span>';

            tr.innerHTML = `
                <td>
                    <div class="site-info-wrapper">
                        <span class="status-indicator ${site.status === 'online' ? 'bg-success' : 'bg-danger'}"></span>
                        <div>
                            <span class="site-name">${Utils.escapeHTML(site.name)}</span>
                            <span class="site-url">${Utils.escapeHTML(site.url)}</span>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-light text-dark border">${site.version || '-'}</span></td>
                <td><span class="text-muted">${site.php || '-'}</span></td>
                <td>${updateHTML}</td>
                <td class="text-muted small">${App.formatDate(site.last_check)}</td>
                <td onclick="event.stopPropagation()">
                    <button class="btn-icon" onclick="App.refreshSite('${site.id}', event)"><i class="ph ph-arrows-clockwise"></i></button>
                    <button class="btn-icon" onclick="App.loginToSite('${site.id}')"><i class="ph ph-sign-in"></i></button>
                </td>`;
            tbody.appendChild(tr);
        });
    },

    showDetails(siteId) {
        const site = App.sites.find(s => s.id === siteId);
        const modal = document.getElementById('siteDetailsModal');
        if (!site || !modal) return;

        // Basis-Daten
        document.getElementById('modal-site-name').innerText = site.name;
        document.getElementById('modal-site-url').innerText = site.url;
        document.getElementById('modal-wp-version').innerText = site.version || '-';
        document.getElementById('modal-php-version').innerText = site.php || '-';
        document.getElementById('modal-ip-address').innerText = site.ip || '-';

        const loginBtn = document.getElementById('modal-login-btn');
        if (loginBtn) loginBtn.onclick = () => App.loginToSite(site.id);

        const pluginCont = document.getElementById('plugin-list-container');
        const themeCont = document.getElementById('theme-list-container');
        const section = document.getElementById('modal-updates-section');

        pluginCont.innerHTML = ''; 
        themeCont.innerHTML = '';
        
        // Flexibler Zugriff auf die Listen (falls sie verschachtelt gespeichert wurden)
        const pList = site.plugin_list || site.updates?.plugin_list || [];
        const tList = site.theme_list || site.updates?.theme_list || [];

        if (pList.length > 0 || tList.length > 0) {
            section.style.display = 'block';
            
            if(pList.length > 0) {
                pluginCont.innerHTML = '<h4 class="section-title">Plugins</h4>';
                pList.forEach(p => {
                    pluginCont.innerHTML += `
                        <div class="update-list-item">
                            <span><strong>${Utils.escapeHTML(p.name)}</strong></span>
                            <span class="version-change">${p.old_version} <i class="ph ph-arrow-right"></i> ${p.new_version}</span>
                        </div>`;
                });
            }

            if(tList.length > 0) {
                themeCont.innerHTML += '<h4 class="section-title" style="margin-top:1rem;">Themes</h4>';
                tList.forEach(t => {
                    themeCont.innerHTML += `
                        <div class="update-list-item">
                            <span><strong>${Utils.escapeHTML(t.name)}</strong></span>
                            <span class="version-change">${t.old_version} <i class="ph ph-arrow-right"></i> ${t.new_version}</span>
                        </div>`;
                });
            }
        } else {
            section.style.display = 'none';
        }

        modal.showModal();
    },
    setLoading(isLoading) {
        const tbody = document.getElementById('sites-tbody');
        if (tbody && isLoading) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:3rem;"><i class="ph ph-circle-notch ph-spin" style="font-size:2rem; color:var(--primary);"></i></td></tr>';
    }
};
