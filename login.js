document.getElementById("loginForm").addEventListener("submit", function (e) {
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();

    if (email === "" || password === "") {
        e.preventDefault();
        alert("Please enter both email and password");
    }
});