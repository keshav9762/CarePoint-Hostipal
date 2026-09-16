// Login Dropdown Toggle Function (Global - defined first)
function toggleLoginDropdown() {
  const dropdown = document.getElementById("loginDropdown");
  if (dropdown) {
    dropdown.classList.toggle("show");
  }
}

// Make it available globally
window.toggleLoginDropdown = toggleLoginDropdown;

// Run after DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {

  // ------------------------------
  // MENU BAR TOGGLE
  // ------------------------------
  // const menubar = document.getElementById("menubar");
  // const nav = document.querySelector(".nav");

  // if (menubar && nav) {
  //   menubar.addEventListener("click", (e) => {
  //     e.stopPropagation();
  //     nav.classList.toggle("active");
  //     if (nav.classList.contains("active")) {
  //       menubar.classList.remove("fa-bars");
  //       menubar.classList.add("fa-times");
  //     } else {
  //       menubar.classList.remove("fa-times");
  //       menubar.classList.add("fa-bars");
  //     }
  //   });
  // }

  const menubar = document.getElementById("menubar");
const navLinks = document.querySelector(".nav-links");

if (menubar && navLinks) {
  menubar.addEventListener("click", (e) => {
    e.stopPropagation();
    navLinks.classList.toggle("active"); // toggle menu

    // Toggle hamburger icon
    if (navLinks.classList.contains("active")) {
      menubar.classList.remove("fa-bars");
      menubar.classList.add("fa-times");
    } else {
      menubar.classList.remove("fa-times");
      menubar.classList.add("fa-bars");
    }
  });
}


  console.log("Login/Signup page loaded");

  // ------------------------------
  // LOGIN DROPDOWN TOGGLE
  // ------------------------------
  // Close dropdown when clicking outside
  document.addEventListener("click", (e) => {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.querySelector(".login-dropdown-btn");
    if (dropdown && btn) {
      // Check if click is outside both button and dropdown
      if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove("show");
      }
    }
  });

  // ------------------------------
  // DASHBOARD PAGE SWITCHING
  // ------------------------------
  const buttons = document.querySelectorAll(".menu");
  const pages = document.querySelectorAll(".page");

  const setPage = (pageId) => {
    if (!pageId) return;
    buttons.forEach(b => b.classList.remove("active"));
    pages.forEach(p => p.classList.remove("show"));

    const targetBtn = Array.from(buttons).find(b => b.dataset.page === pageId);
    const targetPage = document.getElementById(pageId);

    if (targetBtn) targetBtn.classList.add("active");
    if (targetPage) targetPage.classList.add("show");
  };

  buttons.forEach(btn => {
    btn.addEventListener("click", () => {
      setPage(btn.dataset.page);
    });
  });

  // Open page based on URL hash (e.g., #appointment)
  const initialHash = window.location.hash.replace("#", "");
  if (initialHash) {
    setPage(initialHash);
  }
  window.addEventListener("hashchange", () => {
    const newHash = window.location.hash.replace("#", "");
    setPage(newHash);
  });

});

