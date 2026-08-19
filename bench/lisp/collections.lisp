(do
  (def benchmark-double (fn (n) (* n 2)))
  (reduce + (map benchmark-double (range 20000))))
