(function () {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector("body");
    const selectHeader = document.querySelector("#header");
    if (
      !selectHeader.classList.contains("scroll-up-sticky") &&
      !selectHeader.classList.contains("sticky-top") &&
      !selectHeader.classList.contains("fixed-top")
    )
      return;
    window.scrollY > 100
      ? selectBody.classList.add("scrolled")
      : selectBody.classList.remove("scrolled");
  }

  document.addEventListener("scroll", toggleScrolled);
  window.addEventListener("load", toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector(".mobile-nav-toggle");

  function mobileNavToogle() {
    document.querySelector("body").classList.toggle("mobile-nav-active");
    mobileNavToggleBtn.classList.toggle("bi-list");
    mobileNavToggleBtn.classList.toggle("bi-x");
  }
  mobileNavToggleBtn.addEventListener("click", mobileNavToogle);

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll("#navmenu a").forEach((navmenu) => {
    navmenu.addEventListener("click", () => {
      if (document.querySelector(".mobile-nav-active")) {
        mobileNavToogle();
      }
    });
  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll(".navmenu .toggle-dropdown").forEach((navmenu) => {
    navmenu.addEventListener("click", function (e) {
      e.preventDefault();
      this.parentNode.classList.toggle("active");
      this.parentNode.nextElementSibling.classList.toggle("dropdown-active");
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector("#preloader");
  if (preloader) {
    window.addEventListener("load", () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector(".scroll-top");

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100
        ? scrollTop.classList.add("active")
        : scrollTop.classList.remove("active");
    }
  }
  scrollTop.addEventListener("click", (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  });

  window.addEventListener("load", toggleScrollTop);
  document.addEventListener("scroll", toggleScrollTop);

  /**
   * Animation on scroll function and init
   */
  function aosInit() {
    AOS.init({
      duration: 600,
      easing: "ease-in-out",
      once: true,
      mirror: false,
    });
  }
  window.addEventListener("load", aosInit);

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: ".glightbox",
  });

  /**
   * Initiate Pure Counter
   */
  new PureCounter();

  /**
   * Init swiper sliders
   */
  function initSwiper() {
    document.querySelectorAll(".init-swiper").forEach(function (swiperElement) {
      let config = JSON.parse(
        swiperElement.querySelector(".swiper-config").innerHTML.trim()
      );

      if (swiperElement.classList.contains("swiper-tab")) {
        initSwiperWithCustomPagination(swiperElement, config);
      } else {
        new Swiper(swiperElement, config);
      }
    });
  }

  window.addEventListener("load", initSwiper);

  /**
   * Init isotope layout and filters
   */
  document.querySelectorAll(".isotope-layout").forEach(function (isotopeItem) {
    let layout = isotopeItem.getAttribute("data-layout") ?? "masonry";
    let filter = isotopeItem.getAttribute("data-default-filter") ?? "*";
    let sort = isotopeItem.getAttribute("data-sort") ?? "original-order";

    let initIsotope;
    imagesLoaded(isotopeItem.querySelector(".isotope-container"), function () {
      initIsotope = new Isotope(
        isotopeItem.querySelector(".isotope-container"),
        {
          itemSelector: ".isotope-item",
          layoutMode: layout,
          filter: filter,
          sortBy: sort,
        }
      );
    });

    isotopeItem
      .querySelectorAll(".isotope-filters li")
      .forEach(function (filters) {
        filters.addEventListener(
          "click",
          function () {
            isotopeItem
              .querySelector(".isotope-filters .filter-active")
              .classList.remove("filter-active");
            this.classList.add("filter-active");
            initIsotope.arrange({
              filter: this.getAttribute("data-filter"),
            });
            if (typeof aosInit === "function") {
              aosInit();
            }
          },
          false
        );
      });
  });

  /**
   * Correct scrolling position upon page load for URLs containing hash links.
   */
  window.addEventListener("load", function (e) {
    if (window.location.hash) {
      if (document.querySelector(window.location.hash)) {
        setTimeout(() => {
          let section = document.querySelector(window.location.hash);
          let scrollMarginTop = getComputedStyle(section).scrollMarginTop;
          window.scrollTo({
            top: section.offsetTop - parseInt(scrollMarginTop),
            behavior: "smooth",
          });
        }, 100);
      }
    }
  });

  /**
   * Navmenu Scrollspy
   */
  let navmenulinks = document.querySelectorAll(".navmenu a");

  function navmenuScrollspy() {
    navmenulinks.forEach((navmenulink) => {
      if (!navmenulink.hash) return;
      let section = document.querySelector(navmenulink.hash);
      if (!section) return;
      let position = window.scrollY + 200;
      if (
        position >= section.offsetTop &&
        position <= section.offsetTop + section.offsetHeight
      ) {
        document
          .querySelectorAll(".navmenu a.active")
          .forEach((link) => link.classList.remove("active"));
        navmenulink.classList.add("active");
      } else {
        navmenulink.classList.remove("active");
      }
    });
  }
  window.addEventListener("load", navmenuScrollspy);
  document.addEventListener("scroll", navmenuScrollspy);

  // Navbar Index
  document.addEventListener("DOMContentLoaded", function () {
    const userDropdown = document.getElementById("userDropdown");
    const dropdownMenu = document.getElementById("dropdownMenu");

    if (userDropdown && dropdownMenu) {
      userDropdown.addEventListener("mouseenter", function () {
        dropdownMenu.classList.add("show");
      });

      userDropdown.addEventListener("mouseleave", function () {
        dropdownMenu.classList.remove("show");
      });

      dropdownMenu.addEventListener("mouseenter", function () {
        dropdownMenu.classList.add("show");
      });

      dropdownMenu.addEventListener("mouseleave", function () {
        dropdownMenu.classList.remove("show");
      });
    } else {
      console.log(
        "Dropdown tidak ditemukan. Mungkin halaman ini tidak memerlukan dropdown."
      );
    }
  });

  // Beasiswa & Program
  document.addEventListener("DOMContentLoaded", function () {
    console.log("JavaScript loaded");

    function resetModalAddBeasiswa() {
      console.log("Reset modal tambah beasiswa...");
      const container = document.querySelector("#benefit-container");
      if (container) {
        container.innerHTML = `
                <div class="benefit-group d-flex mb-2">
                    <input type="text" name="benefitBeasiswa[]" class="form-control benefit-input" placeholder="Benefit Beasiswa" required>
                    <button type="button" class="btn btn-danger btn-sm ms-2 remove-benefit" style="display: none;">Hapus</button>
                </div>
            `;
      } else {
        console.error("❌ Error: #benefit-container tidak ditemukan!");
      }
    }

    function resetModalEditBeasiswa(modal) {
      console.log("Reset modal edit beasiswa...");
      const container = modal.querySelector(".benefit-container");
      if (container) {
        const firstBenefit = container.querySelector(".benefit-group");
        if (!firstBenefit) {
          container.innerHTML = `
                    <div class="benefit-group d-flex mb-2">
                        <input type="text" name="benefitBeasiswa[]" class="form-control benefit-input" placeholder="Benefit Beasiswa" required>
                        <button type="button" class="btn btn-danger btn-sm ms-2 remove-benefit" style="display: none;">Hapus</button>
                    </div>
                `;
        }
      } else {
        console.error(
          "❌ Error: Container benefit di modal edit tidak ditemukan!"
        );
      }
    }

    function updateRemoveButtons(container) {
      const removeButtons = container.querySelectorAll(".remove-benefit");
      removeButtons.forEach((button, index) => {
        button.style.display = index > 0 ? "inline-block" : "none";
      });
    }

    function removeBenefit(button) {
      const container = button.closest(".benefit-container");
      if (container) {
        button.parentElement.remove();
        updateRemoveButtons(container);
      }
    }

    function addBenefit(containerSelector) {
      const container = document.querySelector(containerSelector);
      if (!container) {
        console.error("❌ Error: Container benefit tidak ditemukan!");
        return;
      }

      const newInput = document.createElement("div");
      newInput.classList.add("benefit-group", "d-flex", "mb-2");
      newInput.innerHTML = `
            <input type="text" name="benefitBeasiswa[]" class="form-control benefit-input" placeholder="Benefit Beasiswa" required>
            <button type="button" class="btn btn-danger btn-sm ms-2 remove-benefit">Hapus</button>
        `;
      container.appendChild(newInput);
      updateRemoveButtons(container);
    }

    const page = window.location.pathname.split("/").pop();

    if (page === "viewBeasiswa.php") {
      console.log("Halaman: Beasiswa");

      // Event untuk modal tambah
      const modalAddBeasiswa = document.querySelector("#modalAddBeasiswa");
      if (modalAddBeasiswa) {
        modalAddBeasiswa.addEventListener("show.bs.modal", function () {
          modalAddBeasiswa.removeAttribute("aria-hidden");
          modalAddBeasiswa.removeAttribute("inert");
        });

        modalAddBeasiswa.addEventListener("hidden.bs.modal", function () {
          setTimeout(() => {
            modalAddBeasiswa.setAttribute("aria-hidden", "true");
            modalAddBeasiswa.setAttribute("inert", "");
          }, 300); // Tambahkan delay untuk memastikan modal benar-benar hilang

          // Mengembalikan fokus ke tombol pemicu modal
          const openModalButton = document.querySelector(
            '[data-bs-target="#modalAddBeasiswa"]'
          );
          if (openModalButton) {
            setTimeout(() => openModalButton.focus(), 350); // Fokuskan kembali setelah modal benar-benar tertutup
          }
        });
      }

      // Event untuk modal edit
      document
        .querySelectorAll("[id^='modalEditBeasiswa']")
        .forEach((modal) => {
          modal.addEventListener("show.bs.modal", function () {
            modal.removeAttribute("aria-hidden");
            modal.removeAttribute("inert");
            resetModalEditBeasiswa(modal);
          });

          modal.addEventListener("hidden.bs.modal", function () {
            setTimeout(() => {
              modal.setAttribute("aria-hidden", "true");
              modal.setAttribute("inert", "");
            }, 100);
          });
        });

      // Event listener untuk tombol tambah dan hapus benefit
      document.addEventListener("click", function (event) {
        if (event.target.id === "add-benefit") {
          addBenefit("#benefit-container");
        }
        if (event.target.classList.contains("add-benefit")) {
          const beasiswaId = event.target.getAttribute("data-id");
          addBenefit(`#benefit-container-${beasiswaId}`);
        }
        if (event.target.classList.contains("remove-benefit")) {
          removeBenefit(event.target);
        }
      });
    }

    if (page === "viewProgram.php") {
      console.log("Halaman: Program");

      let paketIndex = 1;

      function tambahPaket(container, index) {
        if (!container) {
          console.error("❌ Error: Container program tidak ditemukan!");
          return;
        }

        const newPaket = document.createElement("div");
        newPaket.classList.add("program-group");
        newPaket.innerHTML = `
              <br>
              <h6>Paket Program</h6>
              <button type="button" class="btn btn-danger btn-sm remove-paket">Hapus Paket</button>
              <div class="form-group">
                  <label>Nama Paket</label>
                  <input type="text" name="paketProgram[]" class="form-control" required>
              </div>
              <div class="form-group">
                  <label>Price Program</label>
                  <input type="text" name="priceProgram[]" class="form-control" required>
              </div>
              <div class="form-group">
                  <label>unit Program</label>
                  <input type="text" name="unitProgram[]" class="form-control" required>
              </div>
              <div class="form-group waktu-container">
                  <label>Waktu Program</label>
                  <div class="d-flex">
                      <input type="text" name="waktuProgram[${index}][]" class="form-control" required>
                      <button type="button" class="btn btn-success btn-sm ms-2 add-waktu">+</button>
                  </div>
              </div>
              <div class="form-group benefit-container">
                  <label>Benefit Program</label>
                  <div class="d-flex">
                      <input type="text" name="benefitProgram[${index}][]" class="form-control" required>
                      <button type="button" class="btn btn-success btn-sm ms-2 add-benefit">+</button>
                  </div>
              </div>
              <hr>
          `;
        container.appendChild(newPaket);
      }

      // Tambah Paket di Modal Add
      const addPaketButton = document.getElementById("add-paket");
      if (addPaketButton) {
        addPaketButton.addEventListener("click", function () {
          const programContainer = document.getElementById("program-container");
          if (programContainer) {
            tambahPaket(programContainer, paketIndex++);
          } else {
            console.error(
              "❌ Error: Element #program-container tidak ditemukan!"
            );
          }
        });
      }

      // Tambah Paket di Modal Edit
      document.querySelectorAll(".add-edit-paket").forEach((button) => {
        button.addEventListener("click", function () {
          const idProgram = this.dataset.idprogram;
          const editContainer = document.getElementById(
            `edit-program-container-${idProgram}`
          );

          if (editContainer) {
            tambahPaket(editContainer, paketIndex++);
          } else {
            console.error(
              `❌ Error: Container edit-program-container-${idProgram} tidak ditemukan!`
            );
          }
        });
      });

      // Event Delegation untuk Tambah/Hapus Waktu dan Benefit
      document.addEventListener("click", function (event) {
        if (event.target.classList.contains("remove-paket")) {
          event.target.closest(".program-group").remove();
        }

        if (event.target.classList.contains("add-waktu")) {
          const waktuContainer = event.target.closest(".waktu-container");
          if (waktuContainer) {
            tambahField(waktuContainer, "waktuProgram");
          } else {
            console.error(
              "❌ Error: Container untuk waktuProgram tidak ditemukan!"
            );
          }
        }

        if (event.target.classList.contains("add-benefit")) {
          const benefitContainer = event.target.closest(".benefit-container");
          if (benefitContainer) {
            tambahField(benefitContainer, "benefitProgram");
          } else {
            console.error(
              "❌ Error: Container untuk benefitProgram tidak ditemukan!"
            );
          }
        }

        if (event.target.classList.contains("remove-field")) {
          event.target.parentElement.remove();
        }
      });

      function tambahField(container, fieldName) {
        const inputName = container.querySelector("input")?.name;
        if (!inputName) {
          console.error(
            `❌ Error: Input field untuk ${fieldName} tidak ditemukan!`
          );
          return;
        }

        const newInput = document.createElement("div");
        newInput.classList.add("d-flex", "mt-2");
        newInput.innerHTML = `
              <input type="text" name="${inputName}" class="form-control" required>
              <button type="button" class="btn btn-danger btn-sm ms-2 remove-field">-</button>
          `;
        container.appendChild(newInput);
      }

      // Mengelola modal aria-hidden issue untuk semua modal di halaman ini
      document.querySelectorAll(".modal").forEach((modal) => {
        modal.addEventListener("show.bs.modal", function () {
          modal.removeAttribute("aria-hidden");
          modal.removeAttribute("inert");

          // Pastikan elemen pertama dalam modal mendapat fokus
          const firstInput = modal.querySelector(
            "input, button, textarea, select"
          );
          if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
          }
        });

        modal.addEventListener("hidden.bs.modal", function () {
          // Tambahkan kembali atribut aria-hidden dan inert setelah modal ditutup
          setTimeout(() => {
            modal.setAttribute("aria-hidden", "true");
            modal.setAttribute("inert", "");
          }, 100);

          // Kembalikan fokus ke tombol yang membuka modal
          const openModalButton = document.querySelector(
            `[data-bs-target="#${modal.id}"]`
          );
          if (openModalButton) {
            setTimeout(() => openModalButton.focus(), 150);
          }
        });
      });
    }
  });
})();