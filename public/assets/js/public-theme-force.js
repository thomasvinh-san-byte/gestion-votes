// Force dark theme for projection/room display (DISP-01 requirement)
document.documentElement.setAttribute('data-theme', 'dark');
try { localStorage.setItem('ag-vote-theme', 'dark'); } catch(e) {}
