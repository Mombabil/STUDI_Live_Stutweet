function togglePassword() {
  const passwordInput = document.querySelector("#user_password");
  passwordInput.type = passwordInput.type === "text" ? "password" : "text";

  //   gestion de l'icone oeil fermé/ouvert
  const eyeIcon = document.querySelector(".bi-eye");
  eyeIcon.className =
    eyeIcon.className === "bi bi-eye d-none" ? "bi bi-eye" : "bi bi-eye d-none";

  const eyeSlashIcon = document.querySelector(".bi-eye-slash");
  eyeSlashIcon.className =
    eyeSlashIcon.className === "bi bi-eye-slash d-none"
      ? "bi bi-eye-slash"
      : "bi bi-eye-slash d-none";
}
