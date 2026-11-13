"""Spanish and English versions must stay in sync: same sections and same code blocks."""
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAIRS = [("README.es.md", "README.md"), ("docs/install.md", "docs/install.en.md"), ("docs/how-it-works.md", "docs/how-it-works.en.md")]


def shape(path):
    text = (ROOT / path).read_text(encoding="utf-8")
    headings = [len(m.group(1)) for m in re.finditer(r"^(#{1,6}) ", text, re.M)]
    blocks = re.findall(r"^```.*?^```", text, re.M | re.S)
    return headings, len(blocks), text


class BilingualDocs(unittest.TestCase):
    def test_pairs_exist_and_match(self):
        for es, en in PAIRS:
            with self.subTest(pair=(es, en)):
                h_es, b_es, t_es = shape(es)
                h_en, b_en, t_en = shape(en)
                self.assertEqual(h_es, h_en, "different heading structure")
                self.assertEqual(b_es, b_en, "different number of code blocks")
                self.assertIn(Path(en).name, t_es, "Spanish version must link to the English one")
                self.assertIn(Path(es).name, t_en, "English version must link to the Spanish one")


if __name__ == "__main__":
    unittest.main()
