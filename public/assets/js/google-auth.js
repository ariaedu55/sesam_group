(function () {
    var btn = document.querySelector("[data-google-signin]");
    var cfgEl = document.querySelector("[data-firebase-auth]");
    if (!btn || !cfgEl) {
        return;
    }

    function config() {
        try {
            return JSON.parse(cfgEl.getAttribute("data-firebase-auth") || "{}");
        } catch (err) {
            return {};
        }
    }

    function fail(message) {
        window.alert(message);
        btn.disabled = false;
    }

    function postToken(token) {
        var form = document.createElement("form");
        form.method = "post";
        form.action = cfgEl.getAttribute("data-google-action") || "/connexion/google";
        form.style.display = "none";
        [
            ["_csrf", cfgEl.getAttribute("data-csrf") || ""],
            ["id_token", token],
            ["from", cfgEl.getAttribute("data-from") || "login"],
            ["service", cfgEl.getAttribute("data-service") || ""],
        ].forEach(function (pair) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = pair[0];
            input.value = pair[1];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    btn.addEventListener("click", function () {
        if (typeof firebase === "undefined" || !firebase.auth) {
            fail("Google n'a pas pu s'ouvrir. Rechargez la page.");
            return;
        }
        btn.disabled = true;
        var cfg = config();
        if (!firebase.apps.length) {
            firebase.initializeApp(cfg);
        }
        var provider = new firebase.auth.GoogleAuthProvider();
        provider.setCustomParameters({ prompt: "select_account" });
        provider.addScope("email");
        provider.addScope("profile");
        firebase.auth().signInWithPopup(provider).then(function (result) {
            if (!result || !result.user) {
                throw new Error("empty");
            }
            return result.user.getIdToken();
        }).then(function (token) {
            postToken(token);
        }).catch(function (err) {
            btn.disabled = false;
            if (err && (err.code === "auth/popup-closed-by-user" || err.code === "auth/cancelled-popup-request")) {
                return;
            }
            var code = err && err.code ? err.code : "inconnu";
            var messages = {
                "auth/popup-blocked": "Votre navigateur a bloqué la fenêtre Google. Autorisez les fenêtres pop-up pour sesam-tech.com, puis réessayez.",
                "auth/network-request-failed": "Connexion Internet interrompue. Vérifiez votre réseau, puis réessayez.",
                "auth/web-storage-unsupported": "Votre navigateur bloque la connexion Google (navigation privée ou protection contre le suivi stricte). Essayez en navigation normale ou utilisez votre e-mail.",
                "auth/unauthorized-domain": "Ce domaine n'est pas autorisé pour la connexion Google. Utilisez l'adresse https://sesam-tech.com.",
                "auth/operation-not-allowed": "La connexion Google n'est pas activée pour le moment. Utilisez votre e-mail et votre mot de passe.",
                "auth/account-exists-with-different-credential": "Un compte existe déjà avec cet e-mail. Connectez-vous avec votre mot de passe.",
            };
            fail((messages[code] || "Connexion Google impossible pour le moment. Réessayez, ou utilisez votre e-mail et votre mot de passe.") + " (code : " + code + ")");
        });
    });
})();
