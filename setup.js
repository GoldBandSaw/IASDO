document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const form = document.querySelector("#setup-form");
  if (!form) return;
  const username = document.querySelector("#setup-username");
  username.value = params.get("username") || "";
  form.addEventListener("submit", async event => {
    event.preventDefault();
    const status = document.querySelector("#setup-status");
    const password = document.querySelector("#setup-password").value;
    
    if (password.length < 10) {
      status.className = "auth-status error";
      status.textContent = "Le mot de passe doit contenir au moins 10 caractères.";
      return;
    }
    if (!/[a-zA-Z]/.test(password)) {
      status.className = "auth-status error";
      status.textContent = "Le mot de passe doit contenir au moins une lettre.";
      return;
    }
    if (!/\d/.test(password)) {
      status.className = "auth-status error";
      status.textContent = "Le mot de passe doit contenir au moins un chiffre.";
      return;
    }

    if (password !== document.querySelector("#setup-confirm").value) {
      status.className = "auth-status error";
      status.textContent = "Les mots de passe ne correspondent pas.";
      return;
    }
    
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Activation…";
    }
    status.className = "auth-status info";
    status.textContent = "Activation…";
    
    try {
      const response = await fetch("/api/auth/setup", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username: username.value, token: params.get("token") || "", password }) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Activation impossible.");
      window.location.href = "/login.php";
    } catch (error) {
      status.className = "auth-status error";
      status.textContent = error.message;
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Activer le compte";
      }
    }
  });
});
