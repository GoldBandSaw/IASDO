const params = new URLSearchParams(window.location.search);
const form = document.querySelector("#setup-form");
const username = document.querySelector("#setup-username");
username.value = params.get("username") || "";
form.addEventListener("submit", async event => {
  event.preventDefault();
  const status = document.querySelector("#setup-status");
  const password = document.querySelector("#setup-password").value;
  if (password !== document.querySelector("#setup-confirm").value) {
    status.textContent = "Les mots de passe ne correspondent pas.";
    return;
  }
  status.textContent = "Activation…";
  try {
    const response = await fetch("/api/auth/setup", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ username: username.value, token: params.get("token") || "", password }) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Activation impossible.");
    window.location.href = "/login.php";
  } catch (error) {
    status.textContent = error.message;
  }
});
