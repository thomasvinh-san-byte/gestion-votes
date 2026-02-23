(function() {
    'use strict';

    const tabs = document.querySelectorAll('.help-tab');
    const sections = document.querySelectorAll('.faq-section');
    const items = document.querySelectorAll('.faq-item');
    const searchInput = document.getElementById('faqSearch');

    // =========================================================================
    // ROLE-BASED VISIBILITY
    // =========================================================================

    function hasAccess(requiredRoles) {
      if (!requiredRoles) return true;
      if (!window.Auth || !window.Auth.enabled) return true;
      if (window.Auth.hasAccess) {
        return window.Auth.hasAccess(
          requiredRoles,
          window.Auth.role,
          window.Auth.meetingRoles || []
        );
      }
      const role = window.Auth.role;
      if (!role) return false;
      if (role === 'admin') return true;
      const roles = requiredRoles.split(',').map(r => r.trim());
      return roles.includes(role);
    }

    function applyRoleVisibility() {
      document.querySelectorAll('[data-required-role]').forEach(el => {
        const required = el.getAttribute('data-required-role');
        if (!hasAccess(required)) {
          el.style.display = 'none';
        } else {
          el.style.display = '';
        }
      });

      const activeTab = document.querySelector('.help-tab.active');
      if (activeTab && activeTab.style.display === 'none') {
        const allTab = document.querySelector('.help-tab[data-tab="all"]');
        if (allTab) {
          tabs.forEach(t => t.classList.remove('active'));
          allTab.classList.add('active');
          filterContent('all', searchInput.value);
        }
      }
    }

    // =========================================================================
    // TAB SWITCHING
    // =========================================================================

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const category = tab.dataset.tab;
        filterContent(category, searchInput.value);
      });
    });

    // =========================================================================
    // FAQ ITEM TOGGLE
    // =========================================================================

    items.forEach(item => {
      const question = item.querySelector('.faq-question');
      question.addEventListener('click', () => {
        item.classList.toggle('open');
      });
    });

    // =========================================================================
    // SEARCH
    // =========================================================================

    searchInput.addEventListener('input', () => {
      const activeTab = document.querySelector('.help-tab.active');
      const category = activeTab ? activeTab.dataset.tab : 'all';
      filterContent(category, searchInput.value);
    });

    function filterContent(category, search) {
      const searchLower = search.toLowerCase();

      sections.forEach(section => {
        const sectionCategory = section.dataset.category;
        const matchesCategory = category === 'all' || sectionCategory === category;
        const requiredRole = section.getAttribute('data-required-role');
        const hasRoleAccess = hasAccess(requiredRole);

        if (!matchesCategory || !hasRoleAccess) {
          section.classList.add('hidden');
          return;
        }

        const sectionItems = section.querySelectorAll('.faq-item');
        let hasVisibleItems = false;

        sectionItems.forEach(item => {
          const question = item.querySelector('.faq-question span').textContent.toLowerCase();
          const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
          const searchData = (item.dataset.search || '').toLowerCase();
          const matchesSearch = !searchLower ||
            question.includes(searchLower) ||
            answer.includes(searchLower) ||
            searchData.includes(searchLower);

          if (matchesSearch) {
            item.classList.remove('hidden');
            hasVisibleItems = true;
          } else {
            item.classList.add('hidden');
          }
        });

        if (hasVisibleItems) {
          section.classList.remove('hidden');
        } else {
          section.classList.add('hidden');
        }
      });
    }

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    if (window.Auth && window.Auth.ready) {
      window.Auth.ready.then(applyRoleVisibility);
    } else {
      setTimeout(applyRoleVisibility, 500);
    }
  })();
