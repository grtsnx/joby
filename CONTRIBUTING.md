# 🌟 How to Help with Joby Sync

Thank you for helping! We love your help. ❤️ 

With the release of **v2.0**, we have moved to a more modern, flexible architecture and a premium design system.

---

## 🏗️ 1. How to add your code
1.  **Fork** this project to your own account.
2.  **Create a branch** for your work (e.g., `git checkout -b fix-adzuna-mapping`).
3.  **Do your work** and test it.
4.  **Send a Pull Request** (PR) to our `dev` branch.

---

## 🧩 2. Adding New Providers
We now use a **Plug-and-Play Architecture**. If you want to add a new job source (e.g., Indeed, LinkedIn):
1. Create a new class in `includes/providers/` that implements `Joby_Provider_Interface`.
2. Register your provider in the `Joby_API` factory.
3. Keep it lightweight! We prefer providers that provide clean JSON data.

---

## 🖼️ 3. Design Standards (Premium Minimalist)
Joby Sync v2.0 uses a high-end, minimalist aesthetic. Please follow these rules for any UI changes:
- **Spacious**: Use plenty of padding (`20px-40px`) and margins.
- **Rounded**: Use `12px` border-radius for cards and `20px` for buttons.
- **Premium Colors**: 
    - Soft background: `#F5F5F7`
    - High-contrast headers: `#1D1D1F`
    - Accent Blue: `#0071E3`
- **Soft Shadows**: Use very subtle, blurry shadows (no hard black outlines).
- **Inter Typography**: Use system-native fonts with wide tracking.

---

## 🏷️ 4. The "Magic Prefixes"
Always start your PR titles with one of these words to trigger our automated release system:

| Magic Word | What it means |
| :--- | :--- |
| **`feat:`** | A **New Feature** (v.X.1.0) 🚀 |
| **`fix:`** | A **Bug Fix** (v.X.X.1) 🐛 |
| **`docs:`** | **Documentation** updates 📖 |
| **`style:`** | **Visual/UI** updates 🎨 |

---

## 🔒 5. Security & WordPress Rules
- **Sanitization**: Always use `sanitize_text_field()` and `esc_html()`.
- **Nonces**: Use security nonces for ALL AJAX and form submissions.
- **Comments**: Explain the "Why", not just the "What" in your code comments.

---

### That's it! 🥳
Follow these steps, and you'll helping build the best job aggregator for WordPress.
