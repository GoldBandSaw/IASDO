const loginForm = document.querySelector("#login-form");
if (loginForm) loginForm.addEventListener("submit", async event => {
  event.preventDefault();
  const status = document.querySelector("#login-status");
  status.textContent = "Connexion…";
  try {
    const response = await fetch("/api/auth/login", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username: document.querySelector("#username").value, password: document.querySelector("#password").value }) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Connexion impossible.");
    window.location.href = "/index.html";
  } catch (error) {
    status.textContent = error.message;
  }
});
