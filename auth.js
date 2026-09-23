const loginForm = document.querySelector("#login-form");
async function login(username, password = "") {
  const status = document.querySelector("#login-status");
  status.textContent = "Connexion…";
  try {
    const response = await fetch("/api/auth/login", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username, password }) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Connexion impossible.");
    window.location.href = "/index.html";
  } catch (error) {
    status.textContent = error.message;
  }
}
if (loginForm) loginForm.addEventListener("submit", async event => {
  event.preventDefault();
  await login(document.querySelector("#username").value, document.querySelector("#password").value);
});
