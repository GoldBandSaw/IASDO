const loginForm = document.querySelector("#login-form");
async function login(username, password = "") {
  const status = document.querySelector("#login-status");
  const btn = loginForm.querySelector('button[type="submit"]');
  
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Connexion…";
  }
  
  status.textContent = "Connexion…";
  status.className = "auth-status info";
  try {
    const response = await fetch("/api/auth/login", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username, password }) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Connexion impossible.");
    window.location.href = "/index.php";
  } catch (error) {
    status.className = "auth-status error";
    status.textContent = error.message;
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Se connecter";
    }
  }
}
if (loginForm) loginForm.addEventListener("submit", async event => {
  event.preventDefault();
  await login(document.querySelector("#username").value, document.querySelector("#password").value);
});
