(function () {
    document.querySelectorAll("[data-password-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
            var wrap = button.closest(".password-field");
            if (!wrap) {
                return;
            }
            var input = wrap.querySelector("[data-password-input]");
            if (!input) {
                return;
            }
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            button.setAttribute("aria-pressed", show ? "true" : "false");
            button.setAttribute("aria-label", show ? "Masquer le mot de passe" : "Afficher le mot de passe");
            var hint = button.querySelector(".sr-only");
            if (hint) {
                hint.textContent = show ? "Masquer" : "Afficher";
            }
        });
    });
})();
