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
            fail("Connexion Google impossible. Vérifiez que le fournisseur Google est activé dans Firebase.");
        });
    });
})();
