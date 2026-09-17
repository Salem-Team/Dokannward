import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

/** @type {import("eslint").Linter.Config[]} */
const eslintConfig = [
  {
    ignores: [
      ".next/**",
      ".next-build/**",
      "out/**",
      "build/**",
      "admin/**",
      "node_modules/**",
      "public/**",
      "scrape/**",
      "scripts/**",
      "next-env.d.ts",
    ],
  },
  ...nextVitals,
  ...nextTs,
  {
    rules: {
      // Keep refs in sync for event/rAF handlers — common & intentional here.
      "react-hooks/refs": "off",
      "react-hooks/set-state-in-effect": "off",
      "react-hooks/exhaustive-deps": "warn",
      // Tracking pixels / OSM tiles / success art intentionally use <img>.
      "@next/next/no-img-element": "off",
      "jsx-a11y/role-has-required-aria-props": "warn",
      "@typescript-eslint/no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_",
        },
      ],
      "@typescript-eslint/no-explicit-any": "off",
      "react/no-unescaped-entities": "off",
    },
  },
];

export default eslintConfig;
