import "./styles/hidden-characters-font.css";
import { usePanel } from "kirbyuse";
import ProofreaderButton from "./components/ProofreaderButton.vue";
import ProofreaderReviewDialog from "./components/ProofreaderReviewDialog.vue";
import icons from "./icons.js";

const panel = usePanel();

panel.plugin("grommasdietz/proofreader", {
  components: {
    "k-proofreader-review-dialog": ProofreaderReviewDialog,
  },

  viewButtons: {
    proofreader: ProofreaderButton,
  },

  icons,
});
