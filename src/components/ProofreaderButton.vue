<template>
  <k-button
    variant="filled"
    size="sm"
    :icon="loading ? 'loader' : 'ai-generate-text'"
    :theme="loading ? 'info' : success ? 'positive' : error ? 'negative' : null"
    :title="$t('panel.button.proofreader')"
    @click="scanAndReview"
  >
    <template v-if="showText">{{ buttonText }}</template>
  </k-button>
</template>

<script>
export default {
  props: {
    showText: { type: Boolean, default: false },
  },

  data() {
    return {
      loading: false,
      success: false,
      error: false,
      resetTimerId: null,
    };
  },

  computed: {
    target() {
      const path = this.$panel?.view?.path ?? "";

      if (path === "site") {
        return {
          url: window.location.origin + "/kirby-proofreader/site/optimize",
        };
      }

      const match = path.match(/^pages\/(.+)/);
      if (match) {
        const encodedId = match[1].replace(/\//g, "+");

        return {
          url:
            window.location.origin +
            "/kirby-proofreader/pages/" +
            encodedId +
            "/optimize",
        };
      }

      return null;
    },
    buttonText() {
      if (this.loading) return this.$t("panel.button.proofreader.optimizing");
      if (this.success) return this.$t("panel.button.proofreader.success");
      if (this.error) return this.$t("panel.button.proofreader.error");
      return this.$t("panel.button.proofreader");
    },
  },

  beforeUnmount() {
    this.clearReset();
  },

  methods: {
    clearReset() {
      if (this.resetTimerId !== null) {
        clearTimeout(this.resetTimerId);
        this.resetTimerId = null;
      }
    },
    scheduleReset(ms = 3000) {
      this.clearReset();
      this.resetTimerId = window.setTimeout(() => {
        this.success = false;
        this.error = false;
        this.resetTimerId = null;
      }, ms);
    },
    showError() {
      this.error = true;
      this.loading = false;
      this.scheduleReset();
    },
    openReviewDialog(review) {
      this.$panel.dialog.open({
        component: "k-proofreader-review-dialog",
        props: {
          availableRules: review.availableRules ?? [],
          diffs: review.diffs ?? {},
          suggestions: review.suggestions ?? [],
        },
        on: {
          submit: (payload) => {
            this.$panel.dialog.close();
            this.runOptimize(payload);
          },
        },
      });
    },
    async fetchOptimize({ preview = false, rules = null, fields = null } = {}) {
      const target = this.target;

      if (!target) {
        throw new Error("Proofreader target missing");
      }

      const res = await fetch(target.url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "x-language": this.$panel?.language?.code ?? "",
        },
        body: JSON.stringify({ preview, rules, fields }),
      });

      let json = null;
      try {
        json = await res.json();
      } catch {
        // ignore parse errors
      }

      return { res, json };
    },
    async scanAndReview() {
      if (this.loading || !this.target) return;

      this.loading = true;
      this.success = false;
      this.error = false;

      const minDelay = 200;
      const start = Date.now();

      try {
        const { res, json } = await this.fetchOptimize({ preview: true });

        const elapsed = Date.now() - start;
        if (elapsed < minDelay) {
          await new Promise((r) => setTimeout(r, minDelay - elapsed));
        }

        this.loading = false;

        if (!res.ok || json?.status !== "ok") {
          this.showError();
          return;
        }

        const suggestions = json?.suggestions ?? [];
        const changedCount = suggestions.length;

        if (changedCount === 0) {
          this.$panel.dialog.open({
            component: "k-text-dialog",
            props: {
              text: this.$t("panel.button.proofreader.review.none"),
              submitButton: {
                icon: "check",
                text: this.$t("panel.button.proofreader.review.close"),
                theme: "positive",
              },
            },
          });
          return;
        }

        this.openReviewDialog(json);
      } catch {
        this.showError();
      }
    },
    async runOptimize(payload = {}) {
      this.loading = true;
      this.success = false;
      this.error = false;

      const minDelay = 200;
      const start = Date.now();

      try {
        const { res, json } = await this.fetchOptimize({
          preview: false,
          rules: payload.rules ?? null,
          fields: payload.fields ?? null,
        });

        // Enforce minimum visible loading duration
        const elapsed = Date.now() - start;
        if (elapsed < minDelay) {
          await new Promise((r) => setTimeout(r, minDelay - elapsed));
        }

        if (res.ok && json?.status === "ok") {
          this.success = true;
          this.loading = false;
          this.scheduleReset(3000);
          // Short delay lets the editor see the success label before the reload.
          window.setTimeout(() => {
            this.$panel.view.reload();
          }, 800);
        } else {
          this.showError();
        }
      } catch {
        this.showError();
      }
    },
  },
};
</script>
