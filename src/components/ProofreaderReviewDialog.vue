<template>
  <k-dialog
    ref="dialog"
    size="huge"
    :visible="visible"
    :submit-button="submitButton"
    @cancel="$emit('cancel')"
    @submit="submitSelected"
  >
    <k-box v-if="suggestionCount === 0" theme="info">
      {{ $t("panel.button.proofreader.review.none") }}
    </k-box>

    <template v-else>
      <div class="proofreader-review-intro">
        <h2 class="proofreader-review-title">
          {{ $t("panel.button.proofreader.review.title") }}
        </h2>

        <p class="proofreader-review-summary">
          <strong>{{ selectedSuggestionCount }}</strong>
          {{ $t("panel.button.proofreader.review.of") }}
          <strong>{{ suggestionCount }}</strong>
          {{ suggestionCountLabel }}
          {{ $t("panel.button.proofreader.review.in") }}
          <strong>{{ fieldGroups.length }}</strong>
          {{ fieldCountLabel }}.
        </p>

        <p class="proofreader-review-hint">
          {{
            $t(
              hasTitleGroup
                ? "panel.button.proofreader.review.applyHintWithTitle"
                : "panel.button.proofreader.review.applyHint"
            )
          }}
        </p>
      </div>

      <div class="proofreader-review-rules">
        <strong class="proofreader-review-rules-heading">
          {{ $t("panel.button.proofreader.review.rules") }}
        </strong>
        <k-proofreader-rule-toggles
          :options="ruleOptions"
          :value="selectedRules"
          @input="updateSelectedRules"
        />
      </div>

      <div class="proofreader-review-list">
        <template v-for="group in fieldGroups" :key="group.field">
          <section
            class="proofreader-review-field"
            :data-scope="isTitleGroup(group) ? 'title' : 'content'"
            :data-selected="selectedCountForField(group) > 0"
          >
            <div class="proofreader-review-field-header">
              <div class="proofreader-review-field-heading">
                <label
                  class="proofreader-review-field-toggle"
                  :data-selected="isFieldSelected(group.field)"
                >
                  <input
                    :checked="isFieldSelected(group.field)"
                    type="checkbox"
                    @change="toggleField(group.field, $event.target.checked)"
                  />
                  <span class="proofreader-review-field-title">
                    {{ group.fieldLabel }}
                  </span>
                  <span class="proofreader-review-field-count">
                    {{ fieldSelectionInfo(group) }}
                  </span>
                </label>
              </div>

              <k-button
                :disabled="ruleCountForField(group) === 0"
                icon="check"
                size="sm"
                :responsive="true"
                variant="filled"
                @click="submitField(group.field)"
              >
                {{ applyFieldLabel(group) }}
              </k-button>
            </div>

            <div class="proofreader-review-suggestions">
              <article
                v-for="suggestion in group.suggestions"
                :key="suggestion.id"
                class="proofreader-review-suggestion"
                :class="{
                  'proofreader-review-suggestion-disabled':
                    !isSuggestionSelected(suggestion),
                }"
              >
                <div class="proofreader-review-suggestion-header">
                  <span class="proofreader-review-rule">
                    {{ ruleLabel(suggestion.rule) }}
                  </span>

                  <ol
                    v-if="locationCrumbs(suggestion, group).length > 0"
                    class="proofreader-review-location"
                    aria-label="Location"
                  >
                    <li
                      v-for="crumb in locationCrumbs(suggestion, group)"
                      :key="crumb.label"
                    >
                      {{ crumb.label }}
                    </li>
                  </ol>
                </div>

                <div class="proofreader-review-preview">
                  <div
                    class="k-box proofreader-review-preview-row"
                    data-align="start"
                    data-side="before"
                    data-theme="negative"
                    :aria-label="$t('panel.button.proofreader.review.before')"
                  >
                    <span
                      class="proofreader-review-preview-label"
                      aria-hidden="true"
                    >
                      <k-icon type="cancel" />
                    </span>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p
                      v-html="
                        renderDiffExcerpt(
                          suggestion.previewBefore,
                          suggestion.previewAfter,
                          'before'
                        )
                      "
                    ></p>
                  </div>

                  <div
                    class="k-box proofreader-review-preview-row"
                    data-align="start"
                    data-side="after"
                    data-theme="positive"
                    :aria-label="$t('panel.button.proofreader.review.after')"
                  >
                    <span
                      class="proofreader-review-preview-label"
                      aria-hidden="true"
                    >
                      <k-icon type="check" />
                    </span>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <p
                      v-html="
                        renderDiffExcerpt(
                          suggestion.previewBefore,
                          suggestion.previewAfter,
                          'after'
                        )
                      "
                    ></p>
                  </div>
                </div>
              </article>
            </div>
          </section>

          <div
            v-if="isTitleGroup(group) && hasContentFieldGroups"
            :key="`${group.field}-separator`"
            class="proofreader-review-scope-separator"
            role="separator"
          ></div>
        </template>
      </div>
    </template>
  </k-dialog>
</template>

<script>
import ProofreaderRuleToggles from "./ProofreaderRuleToggles.vue";

const RULE_LABELS = {
  unicode: "panel.button.proofreader.rule.unicode",
  dashes: "panel.button.proofreader.rule.dashes",
  ellipsis: "panel.button.proofreader.rule.ellipsis",
  quotes: "panel.button.proofreader.rule.quotes",
  apostrophes: "panel.button.proofreader.rule.apostrophes",
  spaces: "panel.button.proofreader.rule.spaces",
  dimensions: "panel.button.proofreader.rule.dimensions",
};

export default {
  name: "k-proofreader-review-dialog",

  components: {
    "k-proofreader-rule-toggles": ProofreaderRuleToggles,
  },

  props: {
    visible: {
      type: Boolean,
      default: false,
    },
    suggestions: {
      type: Array,
      default: () => [],
    },
    diffs: {
      type: Object,
      default: () => ({}),
    },
    availableRules: {
      type: Array,
      default: () => [],
    },
  },

  emits: ["cancel", "submit"],

  data() {
    return {
      selectedRules: [],
      selectedFields: [],
    };
  },

  computed: {
    suggestionCount() {
      return this.suggestions.length;
    },
    fieldGroups() {
      const groups = new Map();

      for (const suggestion of this.suggestions) {
        if (!groups.has(suggestion.field)) {
          groups.set(suggestion.field, {
            field: suggestion.field,
            fieldLabel: suggestion.fieldLabel ?? suggestion.field,
            count: 0,
            suggestions: [],
          });
        }

        const group = groups.get(suggestion.field);
        group.count++;
        group.suggestions.push(suggestion);
      }

      return [...groups.values()].sort((a, b) => {
        if (String(a.field).toLowerCase() === "title") return -1;
        if (String(b.field).toLowerCase() === "title") return 1;

        return 0;
      });
    },
    ruleOptions() {
      const counts = new Map();

      for (const suggestion of this.suggestions) {
        counts.set(suggestion.rule, (counts.get(suggestion.rule) ?? 0) + 1);
      }

      const rules =
        this.availableRules.length > 0
          ? this.availableRules
          : [...counts.keys()].map((name) => ({ name }));

      return rules.map((rule) => ({
        count: counts.get(rule.name) ?? 0,
        label: rule.label || this.ruleLabel(rule.name),
        name: rule.name,
      }));
    },
    selectedSuggestionCount() {
      return this.suggestions.filter((suggestion) =>
        this.isSuggestionSelected(suggestion)
      ).length;
    },
    suggestionCountLabel() {
      return this.$t(
        this.suggestionCount === 1
          ? "panel.button.proofreader.review.suggestion"
          : "panel.button.proofreader.review.suggestions"
      );
    },
    fieldCountLabel() {
      return this.$t(
        this.fieldGroups.length === 1
          ? "panel.button.proofreader.review.field"
          : "panel.button.proofreader.review.fields"
      );
    },
    isEverythingSelected() {
      return this.selectedSuggestionCount === this.suggestionCount;
    },
    hasContentFieldGroups() {
      return this.fieldGroups.some(
        (group) => this.isTitleGroup(group) === false
      );
    },
    hasTitleGroup() {
      return this.fieldGroups.some((group) => this.isTitleGroup(group));
    },
    hasSelectedTitleSuggestions() {
      return this.suggestions.some(
        (suggestion) =>
          String(suggestion.field).toLowerCase() === "title" &&
          this.isSuggestionSelected(suggestion)
      );
    },
    submitButton() {
      if (this.suggestionCount === 0) return false;

      let label = this.isEverythingSelected
        ? "panel.button.proofreader.review.applyAll"
        : "panel.button.proofreader.review.apply";

      if (this.hasSelectedTitleSuggestions) {
        label = this.isEverythingSelected
          ? "panel.button.proofreader.review.applyAllWithTitle"
          : "panel.button.proofreader.review.applyWithTitle";
      }

      return {
        disabled: this.selectedSuggestionCount === 0,
        icon: "check",
        text: this.$t(label),
        theme: "positive",
      };
    },
  },

  watch: {
    availableRules: {
      handler() {
        this.resetSelection();
      },
    },
    suggestions: {
      handler() {
        this.resetSelection();
      },
      immediate: true,
    },
  },

  methods: {
    resetSelection() {
      this.selectedRules = this.ruleOptions
        .filter((rule) => rule.count > 0)
        .map((rule) => rule.name);
      this.selectedFields = [
        ...new Set(this.suggestions.map(({ field }) => field)),
      ];
    },
    updateSelectedRules(value) {
      this.selectedRules = Array.isArray(value) ? value : [];
    },
    ruleLabel(rule) {
      const key = RULE_LABELS[rule];
      return key ? this.$t(key) : rule;
    },
    fieldSelectionInfo(group) {
      return `${this.selectedCountForField(group)}/${group.count}`;
    },
    applyFieldLabel(group) {
      return this.$t(
        this.isTitleGroup(group)
          ? "panel.button.proofreader.review.applyTitle"
          : "panel.button.proofreader.review.applyField"
      );
    },
    isTitleGroup(group) {
      return String(group.field).toLowerCase() === "title";
    },
    locationCrumbs(suggestion, group) {
      const parts = String(suggestion.pathLabel ?? "")
        .split(/\s*->\s*/)
        .map((part) => part.trim())
        .filter(Boolean);
      const groupLabel =
        group?.fieldLabel ?? suggestion.fieldLabel ?? suggestion.field;

      if (parts[0] === groupLabel) {
        parts.shift();
      }

      return parts.map((label) => ({ label }));
    },
    selectedRulesList() {
      return [...this.selectedRules];
    },
    selectedFieldsList() {
      return [...this.selectedFields];
    },
    selectedCountForField(group) {
      return group.suggestions.filter((suggestion) =>
        this.isSuggestionSelected(suggestion)
      ).length;
    },
    ruleCountForField(group) {
      return group.suggestions.filter((suggestion) =>
        this.selectedRules.includes(suggestion.rule)
      ).length;
    },
    isFieldSelected(field) {
      return this.selectedFields.includes(field);
    },
    toggleField(field, selected) {
      if (selected === true && this.selectedFields.includes(field) === false) {
        this.selectedFields = [...this.selectedFields, field];
        return;
      }

      if (selected === false) {
        this.selectedFields = this.selectedFields.filter(
          (name) => name !== field
        );
      }
    },
    isSuggestionSelected(suggestion) {
      return (
        this.selectedRules.includes(suggestion.rule) &&
        this.selectedFields.includes(suggestion.field)
      );
    },
    submitSelected() {
      this.$emit("submit", {
        rules: this.selectedRulesList(),
        fields: this.selectedFieldsList(),
      });
    },
    submitField(field) {
      this.$emit("submit", {
        rules: this.selectedRulesList(),
        fields: [field],
      });
    },
    escapeHtml(text) {
      return (text ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    },
    diffCharacters(before, after) {
      const from = [...String(before ?? "")].slice(0, 5000);
      const to = [...String(after ?? "")].slice(0, 5000);
      const rows = from.length + 1;
      const cols = to.length + 1;
      const dp = Array.from({ length: rows }, () => new Uint16Array(cols));

      for (let i = 1; i < rows; i++) {
        for (let j = 1; j < cols; j++) {
          dp[i][j] =
            from[i - 1] === to[j - 1]
              ? dp[i - 1][j - 1] + 1
              : Math.max(dp[i - 1][j], dp[i][j - 1]);
        }
      }

      const removed = new Set();
      const inserted = new Set();
      let i = from.length;
      let j = to.length;

      while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && from[i - 1] === to[j - 1]) {
          i--;
          j--;
        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
          inserted.add(j - 1);
          j--;
        } else {
          removed.add(i - 1);
          i--;
        }
      }

      return {
        after: { chars: to, changed: inserted },
        before: { chars: from, changed: removed },
      };
    },
    excerptWindows(changedIndexes, total, contextLength) {
      if (total <= 0) return [];

      const indexes = [...changedIndexes]
        .map((index) => Math.max(0, Math.min(total - 1, index)))
        .sort((a, b) => a - b);

      if (indexes.length === 0) return [];

      const windows = [];

      for (const index of indexes) {
        const start = Math.max(0, index - contextLength);
        const end = Math.min(total, index + contextLength + 1);
        const last = windows[windows.length - 1];

        if (last && start <= last.end + 1) {
          last.end = Math.max(last.end, end);
        } else {
          windows.push({ start, end });
        }
      }

      return windows;
    },
    renderVisibleSegment(text, showPlainSpaces = true) {
      return [...String(text ?? "")]
        .map((char) => {
          if (char === " ") {
            if (showPlainSpaces === false) {
              return " ";
            }

            return '<span class="proofreader-review-hidden-char" title="Space"> </span>';
          }

          if (char === "\u00A0") {
            return '<span class="proofreader-review-hidden-char" title="No-break space">\u00A0</span>';
          }

          if (char === "\u202F") {
            return '<span class="proofreader-review-hidden-char" title="Narrow no-break space">\u202F</span>';
          }

          if (char === "\u2006") {
            return '<span class="proofreader-review-hidden-char" title="Six-per-em space">\u2006</span>';
          }

          if (char === "\u200A") {
            return '<span class="proofreader-review-hidden-char" title="Hair space">\u200A</span>';
          }

          if (char === "\t") {
            return '<span class="proofreader-review-tab-char" title="Tab">TAB</span>';
          }

          return this.escapeHtml(char);
        })
        .join("");
    },
    renderDiffWindow(chars, changed, start, end, tag, className) {
      const output = [];
      let index = start;

      while (index < end) {
        const isChanged = changed.has(index);
        let next = index + 1;

        while (next < end && changed.has(next) === isChanged) {
          next++;
        }

        const segment = chars.slice(index, next).join("");

        output.push(
          isChanged
            ? `<${tag} class="${className}">${this.renderVisibleSegment(
                segment
              )}</${tag}>`
            : this.renderVisibleSegment(segment, false)
        );

        index = next;
      }

      return output.join("");
    },
    renderDiffExcerpt(before, after, side) {
      const from = String(before ?? "");
      const to = String(after ?? "");

      if (from === to) return this.escapeHtml(to);

      const diff = this.diffCharacters(from, to);
      const selected = diff[side];
      const opposite = diff[side === "before" ? "after" : "before"];
      const tag = side === "before" ? "del" : "ins";
      const className =
        side === "before"
          ? "proofreader-review-remove"
          : "proofreader-review-insert";
      const contextLength = 48;
      const changedForWindow =
        selected.changed.size > 0 ? selected.changed : opposite.changed;
      const windows = this.excerptWindows(
        changedForWindow,
        selected.chars.length,
        contextLength
      );

      if (windows.length === 0) {
        return '<span class="proofreader-review-placeholder">∅</span>';
      }

      const output = [];

      windows.forEach((window, index) => {
        if (index === 0 && window.start > 0) {
          output.push("…");
        } else if (index > 0) {
          output.push("…");
        }

        output.push(
          this.renderDiffWindow(
            selected.chars,
            selected.changed,
            window.start,
            window.end,
            tag,
            className
          )
        );

        if (
          index === windows.length - 1 &&
          window.end < selected.chars.length
        ) {
          output.push("…");
        }
      });

      return output.join("");
    },
  },
};
</script>

<style>
@font-face {
  font-display: swap;
  font-family: hidden-characters;
  font-style: normal;
  font-weight: 400;
  src: url("/media/plugins/grommasdietz/hidden-characters/fonts/hidden-characters.woff2")
    format("woff2");
  unicode-range: U+0020, U+00A0, U+2000-200A, U+202F, U+205F, U+E000-E003;
}

.k-dialog-portal:has(.proofreader-review-list) {
  --proofreader-dialog-inset: var(--spacing-1);
  --dialog-width: 60rem;
  --dialog-margin: var(--proofreader-dialog-inset);
}

.k-dialog-portal:has(.proofreader-review-list) .k-dialog[data-size="huge"] {
  max-width: calc(100dvw - (var(--proofreader-dialog-inset) * 2));
  width: min(
    var(--dialog-width),
    calc(100dvw - (var(--proofreader-dialog-inset) * 2))
  );
}

.proofreader-review-intro {
  display: grid;
  gap: 0.25rem;
  margin-bottom: var(--spacing-3);
}

.proofreader-review-title {
  color: var(--color-text);
  font-size: var(--text-lg);
  font-weight: 600;
  line-height: 1.3;
  margin: 0;
}

.proofreader-review-summary {
  color: var(--color-text);
  font-size: var(--text-sm);
  line-height: 1.45;
  margin: 0;
}

.proofreader-review-hint {
  color: var(--color-text-dimmed);
  font-size: var(--text-xs);
  line-height: 1.45;
  margin: 0;
}

.proofreader-review-rules {
  border-bottom: 1px solid var(--color-border);
  display: grid;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-4);
  padding-bottom: var(--spacing-3);
}

.proofreader-review-rules-heading {
  color: var(--color-text-dimmed);
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
}

.proofreader-rule-toggles {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-1);
}

.proofreader-rule-toggle.k-button {
  --button-height: var(--height-xs);
  --button-padding: var(--spacing-2);
  --button-rounded: var(--rounded-sm);

  cursor: pointer;
  gap: var(--spacing-1);
  max-width: 100%;
}

.proofreader-rule-toggle[data-disabled="true"] {
  cursor: not-allowed;
}

.proofreader-rule-toggle-input {
  block-size: 1px;
  inline-size: 1px;
  opacity: 0;
  pointer-events: none;
  position: absolute;
}

.proofreader-rule-toggle:has(.proofreader-rule-toggle-input:focus-visible) {
  outline: 2px solid var(--color-focus);
  outline-offset: 2px;
}

.proofreader-rule-toggle-icon {
  inline-size: 1rem;
  justify-content: center;
  opacity: 1;
}

.proofreader-rule-toggle-text {
  font-weight: 600;
}

.proofreader-rule-toggle-count,
.proofreader-review-field-count {
  align-items: center;
  background: var(--panel-color-back);
  background: color-mix(in srgb, currentColor 12%, transparent);
  border-radius: 999px;
  color: currentColor;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: var(--text-xs);
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  justify-content: center;
  line-height: 1;
  min-width: 1.4rem;
  padding: 0.1875rem 0.375rem;
}

.proofreader-review-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  max-height: min(64vh, 46rem);
  overflow-y: auto;
  padding-bottom: 0.125rem;
}

.proofreader-review-field {
  background: var(--item-color-back);
  border-radius: var(--rounded);
  box-shadow: var(--item-shadow, var(--shadow-sm));
  display: block;
  padding: var(--spacing-4);
}

.proofreader-review-field[data-scope="title"] {
  border: 1px solid var(--color-border);
  box-shadow: none;
}

.proofreader-review-field[data-scope="content"][data-selected="false"] {
  opacity: 0.68;
}

.proofreader-review-scope-separator {
  border-bottom: 1px solid var(--color-border);
  margin: 0;
}

.proofreader-review-field-header {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2) var(--spacing-3);
  justify-content: space-between;
  margin-bottom: var(--spacing-2);
}

.proofreader-review-field-heading {
  flex: 1 1 auto;
  min-width: 0;
}

.proofreader-review-field-toggle {
  align-items: center;
  display: inline-flex;
  gap: var(--spacing-2);
  max-width: 100%;
}

.proofreader-review-field-toggle input {
  flex: 0 0 auto;
}

.proofreader-review-field-title {
  color: var(--color-text);
  font-size: var(--text-sm);
  font-weight: 600;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.proofreader-review-suggestions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.proofreader-review-suggestion {
  display: grid;
  gap: 0.35rem;
}

.proofreader-review-suggestion + .proofreader-review-suggestion {
  border-top: 1px solid var(--color-border);
  padding-top: var(--spacing-3);
}

.proofreader-review-suggestion-disabled {
  opacity: 0.6;
}

.proofreader-review-suggestion-header {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem var(--spacing-2);
  min-width: 0;
}

.proofreader-review-rule {
  color: var(--color-text-dimmed);
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0;
  text-transform: uppercase;
}

.proofreader-review-location {
  --breadcrumb-divider: "›";

  color: var(--color-text-dimmed);
  display: flex;
  flex: 1 1 12rem;
  flex-wrap: wrap;
  gap: 0.0625rem;
  list-style: none;
  margin: 0;
  min-width: 0;
  padding: 0;
}

.proofreader-review-location li {
  min-width: 0;
}

.proofreader-review-location li:not(:last-child)::after {
  content: var(--breadcrumb-divider);
  margin-inline: 0.25rem;
  opacity: 0.35;
}

.proofreader-review-preview {
  color: var(--color-text);
  display: grid;
  font-size: var(--text-xs);
  gap: var(--spacing-1);
  line-height: 1.45;
  word-break: break-word;
}

.proofreader-review-preview-row.k-box {
  --box-height: auto;
  --box-padding-inline: var(--spacing-2);
  --icon-color: var(--theme-color-icon-highlight);

  align-items: start;
  border-radius: var(--rounded-sm);
  block-size: auto;
  display: grid;
  gap: var(--spacing-2);
  grid-template-columns: 1.25rem minmax(0, 1fr);
  line-height: 1.45;
  min-height: 0;
  min-block-size: 0;
  padding: var(--spacing-2);
}

.proofreader-review-preview-label {
  align-items: center;
  block-size: 1.45em;
  color: var(--icon-color);
  display: inline-flex;
  justify-content: center;
  line-height: 1;
}

.proofreader-review-preview p {
  color: inherit;
  line-height: 1.45;
  margin: 0;
  max-block-size: min(14rem, 38vh);
  min-width: 0;
  overflow: auto;
  overflow-wrap: anywhere;
  scrollbar-gutter: stable;
  white-space: pre-wrap;
}

.proofreader-review-remove,
.proofreader-review-insert {
  border-radius: 2px;
  color: inherit;
  font-weight: 600;
  margin: 0 0.1em;
  padding: 0 0.15em;
  text-decoration: none;
}

.proofreader-review-remove {
  background: var(--color-red-550);
}

.proofreader-review-insert {
  background: var(--color-green-600);
}

.proofreader-review-hidden-char,
.proofreader-review-tab-char,
.proofreader-review-placeholder {
  color: var(--icon-color, var(--color-text-dimmed));
}

.proofreader-review-hidden-char {
  font-family: hidden-characters, var(--font-sans);
  font-size: 0.95em;
  font-weight: 400;
  white-space: pre;
}

.proofreader-review-tab-char {
  font-size: 0.72em;
}

.proofreader-review-placeholder {
  font-size: 0.86em;
}

@media (min-width: 46rem) {
  .k-dialog-portal:has(.proofreader-review-list) {
    --proofreader-dialog-inset: var(--spacing-4);
  }
}

@media (max-width: 46rem) {
  .k-dialog-portal:has(.proofreader-review-list) .k-dialog-body {
    --dialog-padding: var(--spacing-3);
  }

  .proofreader-review-field-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .proofreader-review-preview-row {
    gap: var(--spacing-1);
  }

  .proofreader-review-preview p {
    max-block-size: min(10rem, 30vh);
  }
}
</style>
