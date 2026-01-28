// Additive patch for utils.js (do not include via <script>; merge into utils.js).
window.Utils = window.Utils || {};

if (!window.Utils.getStoredApiKey) {
  window.Utils.getStoredApiKey = function(role){
    return (localStorage.getItem(`${role}.api_key`) || "").trim();
  };
}
if (!window.Utils.setStoredApiKey) {
  window.Utils.setStoredApiKey = function(role, key){
    localStorage.setItem(`${role}.api_key`, (key || "").trim());
  };
}
if (!window.Utils.bindApiKeyInput) {
  window.Utils.bindApiKeyInput = function(role, inputEl, onChange){
    if (!inputEl) return;
    inputEl.value = window.Utils.getStoredApiKey(role);
    inputEl.addEventListener("change", () => {
      const k = (inputEl.value || "").trim();
      window.Utils.setStoredApiKey(role, k);
      if (typeof onChange === "function") onChange(k);
    });
  };
}
