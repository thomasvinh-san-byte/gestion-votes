// assets/js/toast.js
(function () {
  if (window.showToast) return;

  function ensureContainer() {
    let c = document.getElementById("toast-container");
    if (!c) {
      c = document.createElement("div");
      c.id = "toast-container";
      c.className = "toast-container";
      document.body.appendChild(c);
    }
    return c;
  }

  function injectStyles() {
    if (document.getElementById("toast-styles")) return;
    const style = document.createElement("style");
    style.id = "toast-styles";
    style.textContent = `
.toast-container {
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-end;
}
.toast {
  min-width: 220px;
  max-width: 320px;
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
  color: #0f172a;
  background: #e5e7eb;
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 150ms ease-out, transform 150ms ease-out;
}
.toast--visible {
  opacity: 1;
  transform: translateY(0);
}
.toast-icon {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
}
.toast--info {
  background: #eff6ff;
}
.toast--info .toast-icon {
  background: #bfdbfe;
}
.toast--success {
  background: #ecfdf5;
}
.toast--success .toast-icon {
  background: #bbf7d0;
}
.toast--error {
  background: #fef2f2;
}
.toast--error .toast-icon {
  background: #fecaca;
}
.toast--warning {
  background: #fffbeb;
}
.toast--warning .toast-icon {
  background: #fde68a;
}`;
    document.head.appendChild(style);
  }

  function showToast(message, opts) {
    opts = opts || {};
    const type = opts.type || "info";
    const duration = typeof opts.duration === "number" ? opts.duration : 3500;

    injectStyles();
    const container = ensureContainer();

    const toast = document.createElement("div");
    toast.className = "toast toast--" + type;

    const icon = document.createElement("div");
    icon.className = "toast-icon";
    icon.textContent =
      type === "success" ? "✓" :
      type === "error" ? "!" :
      type === "warning" ? "!" :
      "i";

    const text = document.createElement("div");
    text.textContent = message;

    toast.appendChild(icon);
    toast.appendChild(text);
    container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add("toast--visible");
    });

    const hide = () => {
      toast.classList.remove("toast--visible");
      setTimeout(() => {
        if (toast.parentNode === container) {
          container.removeChild(toast);
        }
      }, 160);
    };

    if (duration > 0) {
      setTimeout(hide, duration);
    }

    toast.addEventListener("click", hide);
  }

  window.showToast = showToast;
})();